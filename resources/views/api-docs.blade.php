<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Dokumentace – VKV PA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body { margin: 0; }
        .topbar { background: #4f46e5 !important; }
        .topbar-wrapper img { display: none; }
        .topbar-wrapper::before {
            content: 'VKV PA API';
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            padding: 0 1rem;
        }
    </style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js" @cspNonce></script>
<script @cspNonce>
SwaggerUIBundle({
    url: "{{ route('api.docs.spec') }}",
    dom_id: '#swagger-ui',
    presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
    layout: 'BaseLayout',
    deepLinking: true,
    tryItOutEnabled: true,
    filter: false,
});
</script>
</body>
</html>
