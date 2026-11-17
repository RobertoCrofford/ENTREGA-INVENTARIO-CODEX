<?php

return [
    // Direcciones o CIDR del proxy inmediato; vacío deshabilita la confianza.
    'proxies' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))),
        static fn (string $proxy): bool => $proxy !== '',
    )),
];
