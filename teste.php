<?php

require_once 'utils.php';

$utils = new Utils;

$nf1 = array(
    'numeroRps' => '199489',
    'serieRps' => "302",
    'dataEmissao' => "2022-08-15",
    'dataCompetencia' => "2022-08-22",
    'valorServico' => "174.00",
    'cnpj' => $utils->verify_cpf_or_cnpj($utils->format_cpf_and_cnpj_and_cep("12.210.947/0001-069"), '199489'),
    'razaoSocial' => "Leandro e Leandro LTDA",
    'endereco' => "Rodovia Sc 414, 1482",
    'numero' => "",
    'bairro' => "Nossa Senhora da Paz",
    'codigoMunicipio' => '4209102',
    'uf' => "SC",
    'cep' => $utils->format_cpf_and_cnpj_and_cep("88380-000"),
    'email' => "guilherme.medeiros@joinvix.com.br",
    'discriminacao' => "Site Gerenciável - 2799edefdf (22/08/2022 - 21/09/2022)" . PHP_EOL .
        "Adicionais (2799edefdf) - Programação do Layout (valor removido após publicação) (22/08/2022 - 21/09/2022)" . PHP_EOL .
        "Hospedagem Cloud 10GB - leandroeleandro.com.br (22/08/2022 - 21/09/2022)"
);
