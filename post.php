<?php

use \Throwable as Throwable;


require_once("utils.php");
require_once './WHMCS/WhmcsApi.php';

const pathLogError = "Errors.log";

$WhmcsApi = new WhmcsApi;
$gte=0;


function build_nfe(WhmcsApi $WhmcsApi): string
{
    $today = date('d/m/Y');
    $yesterday = date('d/m/Y', strtotime("-1 days"));
    $pattern = "/__e__NFEEMITIDA$/";
    $idsInvoice = [];
    $Nfs = [];

    $invoices = $WhmcsApi->get_invoices(null, 'Paid', null, 'desc', 2500)['invoices']['invoice'];
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
                $cnpjOrCpf = formatCPFAndCNPJAndCEP($WhmcsApi->get_clients_details($k['userid'])['client']['customfields'][0]['value']);
                $isCpfOrCnpj = verify_cpf_or_cnpj($cnpjOrCpf) ? 'cnpj' : 'cpf';

                array_push($Nfs, [
                    'numeroRps' => $k['id'],
                    'serieRps' => "302",
                    'dataEmissao' => $k['date'],
                    'dataCompetencia' => strtotime($k['duedate']) > strtotime(date('Y-m-d')) ? date('Y-m-d') : $k['duedate'],
                    'valorServico' => $k['subtotal'],
                    $isCpfOrCnpj => $cnpjOrCpf,
                    'razaoSocial' => $WhmcsApi->get_clients_details($k['userid'])['client']['companyname'],
                    'endereco' => $WhmcsApi->get_clients_details($k['userid'])['client']['address1'],
                    'numero' => "",
                    'bairro' => $WhmcsApi->get_clients_details($k['userid'])['client']['address2'],
                    'codigoMunicipio' => getCityId($WhmcsApi->get_clients_details($k['userid'])['client']['state'], $WhmcsApi->get_clients_details($k['userid'])['client']['city']),
                    'uf' => $WhmcsApi->get_clients_details($k['userid'])['client']['state'],
                    'cep' => formatCPFAndCNPJAndCEP($WhmcsApi->get_clients_details($k['userid'])['client']['postcode']),
                    'email' => $WhmcsApi->get_clients_details($k['userid'])['client']['email'],
                    'discriminacao' => get_description_invoice($WhmcsApi, $idsInvoice),
                ]);
                $GLOBALS['gte']++;
                //$updateNote = $WhmcsApi->update_invoice_notes_default($k['id']);
                //if ($updateNote['result'] !== 'success') throw new Error('Erro ao atualiza notas da NF de ID ' . $k['id'], 1002);
            }
        }
    }
    return json_encode($Nfs);
}

function get_description_invoice(WhmcsApi $WhmcsApi, array $idsInvoice): string
{
    foreach ($idsInvoice as $i) {
        $items = $WhmcsApi->get_invoice($i)['items']['item'];
        $description = addDescriptionProductOrService($items);
        return $description;
    }
}

function getCityId(string $state, string $city): string
{
    try {
        $IdCity = extractIdCityBaseName($state, $city);
        return $IdCity;
    } catch (Throwable $err) {
        addLogError($err);
    }
}

try {
    $Nfs = build_nfe($WhmcsApi);
    //echo $Nfs;
    echo $GLOBALS['gte'];
} catch (\Throwable $th2) {
    addLogError($th2);
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
