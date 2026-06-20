<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Pásmo v hlavičce EDI (`PBand`) se nepodařilo zařadit mezi známá pásma závodu.
 * Deník se kvůli tomu odmítne (nelze určit kategorii).
 */
class UnknownBandException extends RuntimeException {}
