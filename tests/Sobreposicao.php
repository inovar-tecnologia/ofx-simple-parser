<?php

declare(strict_types=1);

use InovarTecnologia\OfxSimpleParser\LeitorOfx;

mutates(...alvosMutacao());

test('pares do manifesto com mesmo fingerprint por dia', function () {
    $manifest = json_decode(
        file_get_contents(__DIR__.'/Fixtures/ofx/manifest.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $porGrupo = [];
    foreach ($manifest as $entrada) {
        foreach ($entrada['grupos'] ?? [] as $grupo) {
            $porGrupo[$grupo][] = $entrada['arquivo'];
        }
    }

    $leitor = new LeitorOfx(new DateTimeZone('UTC'));

    foreach ($porGrupo as $grupo => $arquivos) {
        if (count($arquivos) < 2) {
            continue;
        }

        $a = fingerprintsPorDia($leitor, $arquivos[0]);
        $b = fingerprintsPorDia($leitor, $arquivos[1]);
        $dias = array_intersect(array_keys($a), array_keys($b));

        foreach ($dias as $dia) {
            if (($a[$dia]['valores'] ?? []) !== ($b[$dia]['valores'] ?? [])) {
                continue;
            }

            expect($a[$dia]['fingerprints'])->toBe($b[$dia]['fingerprints'], "Fingerprints divergem em {$dia} no grupo {$grupo}");
        }
    }
});

/**
 * @return array<string, array{valores: list<int>, fingerprints: list<string>}>
 */
function fingerprintsPorDia(LeitorOfx $leitor, string $arquivo): array
{
    $extrato = $leitor->ler(file_get_contents(__DIR__.'/Fixtures/ofx/'.$arquivo));
    $porDia = [];
    foreach ($extrato->transacoes as $transacao) {
        $dia = $transacao->data->format('Y-m-d');
        $porDia[$dia] ??= ['valores' => [], 'fingerprints' => []];
        $porDia[$dia]['valores'][] = $transacao->valor;
        $porDia[$dia]['fingerprints'][] = $transacao->fingerprint;
    }

    foreach ($porDia as $dia => $dados) {
        sort($dados['valores']);
        sort($dados['fingerprints']);
        $porDia[$dia] = $dados;
    }

    return $porDia;
}
