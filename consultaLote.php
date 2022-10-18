<?php

declare(strict_types=1);


class ConsultaLote
{

    public function consulta_lote(string $protocol): string
    {
        $body = '<?xml version="1.0" encoding="utf-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://nfewshomologacao.joinville.sc.gov.br"><soapenv:Header/><soapenv:Body><ConsultarLoteRpsEnvio x00mlns="http://nfews.joinville.sc.gov.br"><Prestador><CpfCnpj><Cnpj>09387540000144</Cnpj></CpfCnpj><InscricaoMunicipal>92680</InscricaoMunicipal></Prestador>' . "<Protocolo>${protocol}</Protocolo></ConsultarLoteRpsEnvio></soapenv:Body></soapenv:Envelope>";

        $URL_POST = "https://nfemws.joinville.sc.gov.br/NotaFiscal/Servicos.asmx";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $URL_POST);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('SOAPAction: https://nfemws.joinville.sc.gov.br/ConsultarLoteRpsEnvio', 'Content-type: text/xml'));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        $result = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($http_status !== 200) throw new Exception("Erro ao verificar protocolo", 1006);
        curl_close($curl);
        return $this->parse_XML($result, 'Situacao');
    }

    public function parse_XML(string $xml, string $tagtoExtractValue): string
    {
        $doc = new DOMDocument;
        $doc->preservWhiteSpace = false;
        $doc->formatOutput = false;
        $doc->loadXML($xml, LIBXML_NOBLANKS | LIBXML_NOEMPTYTAG);
        $value = $doc->getElementsByTagName($tagtoExtractValue);
        return $value[0]->textContent;
    }
}
