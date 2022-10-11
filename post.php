<?php

declare(strict_types=1);

use \Throwable as Throwable;


require_once "utils.php";
require_once './WHMCS/WhmcsApi.php';

const pathLogError = "Errors.log";

$WhmcsApi = new WhmcsApi;

function build_nfe(WhmcsApi $WhmcsApi): array
{
    $today = date('d/m/Y');
    $yesterday = date('d/m/Y', strtotime("-1 days"));
    $pattern = "/__e__NFEEMITIDA$/";
    $Nfs = [];

    $generator = $WhmcsApi->get_invoices(null, 'Paid', null, 'desc', 5);

    foreach ($generator as $value) {
        foreach ($value['invoices']['invoice'] as $k) {


            $datePaid = new DateTime($k['datepaid']);
            $datePaidD_M_Y = $datePaid->format('d/m/Y');
            if ($today == $datePaidD_M_Y || $yesterday == $datePaidD_M_Y) {
                if (!preg_match($pattern, $k['notes'])) {
                    //EMITE NF
                    /* preenche os campos */
                    $fields = fill_fileds_to_nfe($WhmcsApi, $k);

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
                        'email' => 'guilherme.medeiros@joinvix.com.br', //$fields['email'],
                        'discriminacao' => $fields['discriminacao']
                    ]);
                    $generator = $WhmcsApi->update_invoice_notes_default($k['id']);
                    foreach ($generator as $updateNote) {
                        if ($updateNote['result'] !== 'success') throw new Exception('Erro ao atualizar notas da NF de ID ' . strval($k['id']), 1002);
                    }
                }
            }
        }
    }
    return $Nfs;
}

function getCityId(string $state, string $city): string
{
    try {
        $IdCity = extract_id_city_based_on_name($state, $city);
        return $IdCity;
    } catch (Throwable $err) {
        add_log_error($err);
    }
}

try {
    $Nfs = build_nfe($WhmcsApi);
    //echo json_encode($Nfs);
    //exit;
    execute_post($Nfs);
} catch (\Throwable $th2) {
    add_log_error($th2);
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($NfsToPost));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_status !== 200) throw new Exception("Erro ao enviar requisição POST", 1004);
        curl_close($ch);
        var_dump($result);
        $resultDecode = json_decode($result);
        if ($resultDecode == NULL) {
            var_dump($result);
        } else {
            var_dump($resultDecode);
        }
    } catch (\Throwable $th) {
        foreach ($NfsToPost as $invoice) {
            $generator = $WhmcsApi->update_invoice_notes_default(intval($invoice['numeroRps']), true);

            foreach ($generator as $updateNote) {
                if ($updateNote['result'] !== 'success') throw new Exception('Erro ao atualizar notas da NF de ID ' . $invoice['numeroRps'] . ' no POST', 1005);
            }
        }
        add_log_error($th);
    }
}
