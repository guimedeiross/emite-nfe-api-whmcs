<?php

declare(strict_types=1);

date_default_timezone_set("America/Sao_Paulo");

require 'sendMail.php';
require 'WHMCS/WhmcsApi.php';

class Utils
{

    public function extract_id_city_based_on_name(string $state, string $nameMunicipio): string
    {
        $URL_GET = "https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$state}/distritos";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $URL_GET);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($curl);
        if ($result === "") throw new Exception("Erro ao coletar dados API do IBGE", 1000);
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

    public function format_cpf_and_cnpj_and_cep(string $value): string
    {
        $value = trim($value);
        $value = str_replace(".", "", $value);
        $value = str_replace(",", "", $value);
        $value = str_replace("-", "", $value);
        $value = str_replace("/", "", $value);
        $value = str_replace(" ", "", $value);
        $value = str_replace("\\", "", $value);
        return $value;
    }

    public function verify_cpf_or_cnpj(string $value, ?string $idInvoice = null): bool
    {
        try {
            //Caso seja CNPJ
            if (strlen($value) == 14) {
                return true;
            }
            //Caso seja CPF
            if (strlen($value) == 11) {
                return false;
            }
            throw new Error("Tamanho do campo CPF/CNPJ " . $value . " do cliente " . $idInvoice . " está com problemas", 1003);
        } catch (\Throwable $th) {
            $this->add_log_error($th, $idInvoice);
            return true;
        }
    }

    public function add_log_error(Throwable $err, ?string $idInvoice = null): void
    {
        $WhmcsApi = new WhmcsApi;
        $email = new SendMail;
        $today = date('d-m-Y H:i');
        file_put_contents('Errors.log', "$today " . "{$err->getCode()} " . $err->getMessage() . PHP_EOL, FILE_APPEND);
        $email->sendMail("Erro NFe {$err->getCode()}", "infra@joinvix.com.br", "$today " . "{$err->getCode()} " . $err->getMessage());
        if ($err->getCode() === 1002 || $err->getCode() === 1005 || $err->getCode() === 1009) die();
        try {
            $generator = $WhmcsApi->update_invoice_notes_default(intval($idInvoice), true);
            foreach ($generator as $updateNote) {
                if ($updateNote['result'] !== 'success') throw new Exception('Erro ao atualizar notas da NF de ID ' . $idInvoice . ' no POST', 1009);
            }
        } catch (\Throwable $th) {
            $this->add_log_error($th);
        }
    }

    public function add_description_product_or_service(array $mensagens): string
    {
        $msgFinal = '';
        foreach ($mensagens as $msg) {
            $msgFinal .= $msg['description'] . PHP_EOL;
        }
        $msgFinal = trim($msgFinal);
        return $msgFinal;
    }

    public function fill_fileds_to_nfe(WhmcsApi $WhmcsApi, iterable $invoice): array
    {
        $idsInvoice = [];
        array_push($idsInvoice, $invoice['id']);
        $fields = [];
        $generator = $WhmcsApi->get_clients_details($invoice['userid']);
        foreach ($generator as $clientDetails) {
            $fields['cnpjOrCpf'] = $this->format_cpf_and_cnpj_and_cep($clientDetails['customfields'][0]['value']);
            $fields['isCpfOrCnpj'] = $this->verify_cpf_or_cnpj($fields['cnpjOrCpf'], strval($invoice['id'])) ? 'cnpj' : 'cpf';
            $fields['numeroRps'] = $invoice['id'];
            $fields['dataEmissao'] = $invoice['date'];
            $fields['dataCompetencia'] = strtotime($invoice['duedate']) > strtotime(date('Y-m-d')) ? date('Y-m-d') : $invoice['duedate'];
            $fields['valorServico'] = $invoice['subtotal'];
            $fields['firstNameWithLastName'] = $clientDetails['firstname'] . " " . $clientDetails['lastname'];
            $fields['razaoSocial'] = $clientDetails['client']['companyname'];
            $fields['razaoSocial'] = $this->verify_field_blank($fields['razaoSocial']) ? trim($fields['firstNameWithLastName']) : trim($fields['razaoSocial']);
            $fields['endereco'] = $clientDetails['client']['address1'];
            $fields['bairro'] = $clientDetails['client']['address2'];
            $fields['uf'] = $clientDetails['client']['state'];
            $fields['cep'] = $this->format_cpf_and_cnpj_and_cep($clientDetails['client']['postcode']);
            $fields['email'] = $clientDetails['client']['email'];
            $fields['discriminacao'] = $this->get_description_invoice($WhmcsApi, $idsInvoice);
        }



        return $fields;
    }

    public function get_description_invoice(WhmcsApi $WhmcsApi, array $idsInvoice): string
    {

        foreach ($idsInvoice as $i) {
            $generator = $WhmcsApi->get_invoice($i);
        }
        foreach ($generator as $value) {
            $description = $this->add_description_product_or_service($value['items']['item']);
        }
        return $description;
    }

    public function verify_field_blank(string $field): bool
    {
        if ($field === '') {
            return true;
        } else {
            return false;
        }
    }
}
