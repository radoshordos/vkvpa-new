<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePrispevekRequest;
use App\Models\DiscussionPost;
use App\Models\DiscussionPostPhoto;
use App\Models\EdiRound;
use App\Support\ObrazekProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class DiskuseController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $id = $request->integer('kolo', 0);

        $kolo = ($id > 0 ? EdiRound::query()->find($id) : null)
            ?? EdiRound::query()->orderByDesc('starts_at')->first();

        if (! $kolo) {
            return redirect()->route('home');
        }

        return redirect()->route('diskuse.show', $kolo->id);
    }

    public function show(EdiRound $kolo): View
    {
        $prispevky = DiscussionPost::where('round_id', $kolo->id)
            ->with('photos:id,discussion_post_id,width,height,position') // bez BLOBů
            ->orderBy('created_at')
            ->get();

        $kola = EdiRound::query()
            ->where(function ($query) use ($kolo): void {
                $query->where('starts_at', '>=', now()->subYear()->toDateString())
                    ->orWhereHas('discussion')
                    ->orWhere('id', $kolo->id);
            })
            ->orderByDesc('starts_at')
            ->get();

        return view('pages.diskuse', [
            'active' => 'diskuse.index',
            'kolo' => $kolo,
            'prispevky' => $prispevky,
            'kola' => $kola,
        ]);
    }

    public function store(StorePrispevekRequest $request, EdiRound $kolo): RedirectResponse
    {
        /** @var array<int, UploadedFile> $soubory */
        $soubory = array_values(array_filter(
            $request->file('fotky', []),
            static fn (UploadedFile $f): bool => $f->isValid(),
        ));

        $processor = ObrazekProcessor::create();

        try {
            $zpracovane = [];
            foreach ($soubory as $poradi => $soubor) {
                $path = $soubor->getRealPath();
                if ($path === false) {
                    continue;
                }
                $data = $processor->zpracuj($path);
                $data['position'] = $poradi;
                $zpracovane[] = $data;
            }
        } catch (RuntimeException) {
            return back()
                ->withInput($request->except('fotky'))
                ->withErrors(['fotky' => 'Některý z obrázků se nepodařilo zpracovat. Zkuste prosím jiný soubor.']);
        }

        DB::transaction(function () use ($request, $kolo, $zpracovane): void {
            $post = DiscussionPost::create([
                'round_id' => $kolo->id,
                'callsign' => $request->string('znacka')->value(),
                'name' => $request->filled('jmeno') ? $request->string('jmeno')->trim()->value() : null,
                'body' => $request->string('text')->trim()->value(),
                'ip_address' => $request->ip(),
            ]);

            foreach ($zpracovane as $foto) {
                $post->photos()->create($foto);
            }
        });

        return redirect()
            ->route('diskuse.show', $kolo->id)
            ->with('success', 'Příspěvek byl přidán.');
    }

    /**
     * Servíruje hlavní obrázek z DB. Obsah je neměnný (adresovaný ID), proto
     * dlouhá cache.
     */
    public function foto(DiscussionPostPhoto $foto): Response
    {
        return $this->obrazek($foto->mime_type, $foto->data);
    }

    /** Servíruje náhled (thumbnail) z DB. */
    public function nahled(DiscussionPostPhoto $foto): Response
    {
        return $this->obrazek($foto->mime_type, $foto->thumbnail);
    }

    private function obrazek(string $mime, string $data): Response
    {
        $etag = '"'.md5($data).'"';

        return response($data, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($data),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
        ]);
    }

    public function destroy(DiscussionPost $prispevek): RedirectResponse
    {
        $koloId = $prispevek->round_id;

        // Fotky v `discussion_post_photos` zmizí přes FK cascade (a v testovacím
        // SQLite, kde FK nejsou, je smaže relace ručně).
        DB::transaction(function () use ($prispevek): void {
            $prispevek->photos()->delete();
            $prispevek->delete();
        });

        return redirect()
            ->route('diskuse.show', $koloId)
            ->with('success', 'Příspěvek byl smazán.');
    }
}
