<?php

declare(strict_types=1);

use \Throwable as Throwable;

require_once 'utils.php';

require_once './WHMCS/WhmcsApi.php';
require_once './consultaLote.php';
require_once './getNfClientWithoutPaying.php';

$GLOBALS['utils'] = new Utils;
$GLOBALS['qtdeNFFiltrada'] = 0;

$WhmcsApi = new WhmcsApi;

$start = microtime(true);

function build_nfe(WhmcsApi $WhmcsApi): array
{
    $today = date('d/m/Y');
    $yesterday = date('d/m/Y', strtotime("-1 days"));
    $pattern = "/__e__NFEEMITIDA|__e__PROBLEMA$/";
    $Nfs = [];

    $userIdsCheckNFSemBoleto = consultarClientes();

    $generator = $WhmcsApi->get_invoices(null, null, OrderByGetInvoices::date, 'desc', null);

    foreach ($generator as $value) {
        foreach ($value['invoices']['invoice'] as $k) {
            $creditosNf = floatval($k['credit']);
            if ($creditosNf > 0 && $creditosNf === floatval($k['subtotal']) && !preg_match($pattern, $k['notes'])) {
                try {
                    $generatorUpdateNf = $WhmcsApi->update_invoice_notes_default(intval($k['id']));
                    foreach ($generatorUpdateNf as $updateNote) {
                        if ($updateNote['result'] !== 'success') throw new Exception('Erro ao atualizar campo notas da NF de ID ' . strval($k['id']), 1012);
                    }
                    continue;
                } catch (\Throwable $th) {
                    $GLOBALS['utils']->add_log_error($th);
                }
            }
            //emitir sem confirmação de pagamento
            if (in_array(intval($k["userid"]), $userIdsCheckNFSemBoleto)) {
                //$datePaid = new DateTime($k['datepaid']);
                //$datePaidD_M_Y = $datePaid->format('d/m/Y');
                //if ($today == $datePaidD_M_Y || $yesterday == $datePaidD_M_Y) {
                if (!preg_match($pattern, $k['notes'])) {
                    //EMITE NF
                    /* preenche os campos */
                    $fields = $GLOBALS['utils']->fill_fileds_to_nfe($WhmcsApi, $k);

                    array_push($Nfs, [
                        'numeroRps' => strval($k['id']),
                        'serieRps' => "302",
                        'dataEmissao' => $fields['dataEmissao'],
                        'dataCompetencia' => $fields['dataCompetencia'],
                        'valorServico' => $fields['valorServico'],
                        $fields['isCpfOrCnpj'] => $fields['cnpjOrCpf'],
                        'razaoSocial' => $fields['razaoSocial'],
                        'endereco' => $fields['endereco'],
                        'numero' => "",
                        'bairro' => $fields['bairro'],
                        'codigoMunicipio' => '4209102',
                        'uf' => $fields['uf'],
                        'cep' => $fields['cep'],
                        'email' => $fields['email'],
                        'discriminacao' => $fields['discriminacao']
                    ]);
                    $GLOBALS['qtdeNFFiltrada']++;
                }
            }
        }
    }
    return $Nfs;
}

function getCityId(string $state, string $city): string
{

    try {
        $IdCity = $GLOBALS['utils']->extract_id_city_based_on_name($state, $city);
        return $IdCity;
    } catch (Throwable $err) {
        $GLOBALS['utils']->add_log_error($err);
    }
}

try {
    $Nfs = build_nfe($WhmcsApi);
    echo count($Nfs) > 0 ? var_dump($Nfs) : 'Sem Cliente para emitir sem pagamento';
    exit;
    /*$end = microtime(true);
    $tempo = ($end - $start) / 60;
    file_put_contents('tempo.txt', $tempo . PHP_EOL, FILE_APPEND);
    file_put_contents('qtdeNFFiltrada.txt', $GLOBALS['qtdeNFFiltrada']);
    if (count($Nfs) > 0) execute_post($Nfs[0]);*/
} catch (\Throwable $th2) {
    $GLOBALS['utils']->add_log_error($th2);
}

function verifica_emitiu_nf(string $result): int
{
    $consultaLote = new ConsultaLote;
    $protocol = $consultaLote->parse_XML($result, 'Protocolo');
    $situacao = $consultaLote->consulta_lote($protocol);
    return intval($situacao);
}

function execute_post(array $NfsToPost): void
{
    $WhmcsApi = new WhmcsApi;
    try {
        $URL_SEND_POST = $_ENV['URL_SEND_POST'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $URL_SEND_POST);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array($NfsToPost)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_status !== 200) throw new Exception("Erro ao enviar requisição POST", 1011);
        curl_close($ch);
        $resultDecode = json_decode($result);
        if ($resultDecode == NULL) {
            //quando emite NF entra aqui
            do {
                $situacao = verifica_emitiu_nf($result);
            } while ($situacao === 2);
            if ($situacao !== 4) {
                throw new Exception('Problema ao emitir a NF de ID ' . $NfsToPost['numeroRps'], 1013);
            } else {
                //emite NF
                $generator = $WhmcsApi->update_invoice_notes_default(intval($NfsToPost['numeroRps']));
                foreach ($generator as $updateNote) {
                    if ($updateNote['result'] !== 'success') throw new Exception('Erro ao atualizar notas da NF de ID ' . strval($NfsToPost['numeroRps']), 1014);
                }
                file_put_contents('ProtocolosEmitidosNfSemPagar.txt', $NfsToPost['numeroRps'] . ' - ' . $result . PHP_EOL, FILE_APPEND);
            }
        } else {
            var_dump($resultDecode);
        }
    } catch (\Throwable $th) {
        $generator = $WhmcsApi->update_invoice_notes_default(intval($NfsToPost['numeroRps']), true);
        foreach ($generator as $updateNote) {
            if ($updateNote['result'] !== 'success') throw new Exception('Erro ao atualizar notas da NF de ID ' . $NfsToPost['numeroRps'] . ' no POST', 1015);
        }
        $GLOBALS['utils']->add_log_error($th);
    }
}
