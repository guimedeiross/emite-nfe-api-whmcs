<?php

use \Throwable as Throwable;


require_once("utils.php");
require_once './WHMCS/WhmcsApi.php';

const pathLogError = "Errors.log";

$WhmcsApi = new WhmcsApi;


function build_nfe(WhmcsApi $WhmcsApi): string
{
    $today = date('d/m/Y');
    $yesterday = date('d/m/Y', strtotime("-1 days"));
    $pattern = "/__e__NFEEMITIDA$/";
    $idsInvoice = [];
    $Nfs = [];

    $invoices = $WhmcsApi->get_invoices(null, 'Paid', null, 'desc')['invoices']['invoice'];
    // ordena por data de pagamento
    usort($invoices, function ($a, $b) {
        return (strtotime($a['datepaid']) < strtotime($b['datepaid']) ? -1 : 1);
    });
    foreach ($invoices as $k) {
        $datePaid = new DateTime($k['datepaid']);
        $datePaidD_M_Y = $datePaid->format('d/m/Y');
        if ($today == $datePaidD_M_Y || $yesterday == $datePaidD_M_Y) {
            if (!preg_match($pattern, $k['notes'])) {
                //EMITE NF
                array_push($idsInvoice, $k['id']);
                /* preenche os campos */
                $fields = fill_fileds_to_nfe($WhmcsApi, $k, $idsInvoice);

                array_push($Nfs, [
                    'numeroRps' => $k['id'],
                    'serieRps' => "302",
                    'dataEmissao' => $fields['dataEmissao'],
                    'dataCompetencia' => $fields['dataCompetencia'],
                    'valorServico' => $fields['valorServico'],
                    $fields['isCpfOrCnpj'] => $fields['cnpjOrCpf'],
                    'razaoSocial' => $fields['razaoSocial'],
                    'endereco' => $fields['endereco'],
                    'numero' => "",
                    'bairro' => $fields['bairro'],
                    'codigoMunicipio' => '',
                    'uf' => $fields['uf'],
                    'cep' => $fields['cep'],
                    'email' => $fields['email'],
                    'discriminacao' => $fields['discriminacao'],
                ]);
                //$updateNote = $WhmcsApi->update_invoice_notes_default($k['id']);
                //if ($updateNote['result'] !== 'success') throw new Error('Erro ao atualiza notas da NF de ID ' . $k['id'], 1002);
            }
        }
    }
    return json_encode($Nfs);
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
    echo $Nfs;
} catch (\Throwable $th2) {
    add_log_error($th2);
}

exit;

function execute_post(string $NfsToPost): void
{
    $URL_SEND_POST = $_ENV['URL_SEND_POST'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $URL_SEND_POST);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $NfsToPost);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    var_dump($result);
    $resultDecode = json_decode($result);
    if ($resultDecode == NULL) {
        var_dump($result);
    } else {
        var_dump($resultDecode);
    }
}
