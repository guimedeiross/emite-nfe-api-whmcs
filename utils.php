<?php

date_default_timezone_set("America/Sao_Paulo");

function extractIdCityBaseName(string $state, string $nameMunicipio): string
{
    $URL_GET = "https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$state}/distritos";
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $URL_GET);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    $result = curl_exec($curl);
    if ($result === "") throw new Error("Erro ao coletar dados API do IBGE", 1000);
    curl_close($curl);

    $dados = json_decode($result);
    $nameMunicipio = trim($nameMunicipio);
    $state = trim($state);
    foreach ($dados as $d) {
        if (strtolower($d->municipio->nome) === strtolower($nameMunicipio)) {
            return $d->municipio->id;
        };
    }
}

function formatCPFAndCNPJAndCEP(string $value): string
{
    $value = trim($value);
    $value = str_replace(".", "", $value);
    $value = str_replace(",", "", $value);
    $value = str_replace("-", "", $value);
    $value = str_replace("/", "", $value);
    $value = trim($value);
    return $value;
}

function verify_cpf_or_cnpj(string $value): bool
{
    //Caso seja CNPJ
    if (strlen($value) == 14) {
        return true;
    }
    //Caso seja CPF
    if (strlen($value) == 11) {
        return false;
    }
}

function addLogError(Throwable $err)
{

    $today = date('d-m-Y-H-i');
    file_put_contents(pathLogError, "$today " . "{$err->getCode()} " . $err->getMessage() . PHP_EOL, FILE_APPEND);
    die();
}

function addDescriptionProductOrService(array $mensagens): string
{
    $msgFinal = '';
    foreach ($mensagens as $msg) {
        $msgFinal .= $msg['description'] . PHP_EOL;
    }
    $msgFinal = trim($msgFinal);
    return $msgFinal;
}
