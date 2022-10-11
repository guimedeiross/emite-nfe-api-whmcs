<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 1));
$dotenv->load();

date_default_timezone_set("America/Sao_Paulo");

enum Status
{
    case Active;
    case Inactive;
    case Closed;
}
enum OrderByGetInvoices
{
    case id;
    case invoicenumber;
    case date;
    case duedate;
    case total;
    case status;
}

class WhmcsApi
{
    private function build_query_api(array $arrayValues): iterable
    {
        $arrayValues['username'] = $_ENV['API_IDENTIFIER'];
        $arrayValues['password'] = $_ENV['API_SECRET'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $_ENV['BASE_URL_API_WHMCS']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            http_build_query($arrayValues)
        );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        yield json_decode($response, true);
    }

    public function get_clients(?string $search = null, ?Status $status = null): iterable
    {
        $clients = array(
            'action' => 'GetClients',
            'responsetype' => 'json',
        );
        if ($search) {
            $clients['search'] = $search;
        }
        if ($status) {
            $clients['status'] = $status->name;
        }

        $response = $this->build_query_api($clients);

        return $response;
    }
    public function get_clients_details(int $clientId = null, string $email = null): iterable
    {
        if (!$clientId && !$email) {
            throw new Exception("Cliente Id ou Email são obrigatórios func(getClientsDetails)", 1001);
        }
        $clientsDetails = array(
            'action' => 'GetClientsDetails',
            'responsetype' => 'json'
        );

        if ($clientId) $clientsDetails['clientid'] = $clientId;

        if ($email) $clientsDetails['email'] = $email;

        $response = $this->build_query_api($clientsDetails);

        return $response;
    }

    public function get_invoices(
        ?int $userId = null,
        ?string $status = null,
        ?OrderByGetInvoices $orderBy = null,
        ?string $order = null,
        ?int $limitnum = null,
        ?int $limitstart = null
    ): iterable {
        $invoices = array(
            'action' => 'GetInvoices',
            'responsetype' => 'json',
        );
        if ($userId) $invoices['userid'] = $userId;
        if ($status) $invoices['status'] = $status;
        if ($orderBy) $invoices['orderby'] = $orderBy->name;
        if ($order) $invoices['order'] = $order;
        if ($limitnum) $invoices['limitnum'] = $limitnum;
        if ($limitstart) $invoices['limitstart'] = $limitstart;
        
        $response = $this->build_query_api($invoices);

        return $response;
    }

    public function get_invoice(int $invoiceId): iterable
    {
        $invoice = array(
            'action' => 'GetInvoice',
            'invoiceid' => $invoiceId,
            'responsetype' => 'json',
        );
        $response = $this->build_query_api($invoice);

        return $response;
    }
    public function update_invoice_notes_default(int $invoiceId, ?bool $rollback = false): iterable
    {
        $pattern = "/__e__NFEEMITIDA|[\n\r\n]__e__NFEEMITIDA$/";
        if ($rollback) {
            $generator = $this->get_invoice($invoiceId);
            foreach ($generator as $notes) {
                $notesNovo = preg_replace($pattern, ' ', $notes['notes']);
                $invoice = array(
                    'action' => 'UpdateInvoice',
                    'invoiceid' => $invoiceId,
                    'responsetype' => 'json',
                    'notes' => $notesNovo
                );
            }
        } else {
            $generator = $this->get_invoice($invoiceId);
            foreach ($generator as $notes) {
                $notes = $notes['notes'];
                if ($notes !== "") {
                    $notesNovo = $notes . PHP_EOL . "__e__NFEEMITIDA";
                    $invoice = array(
                        'action' => 'UpdateInvoice',
                        'invoiceid' => $invoiceId,
                        'responsetype' => 'json',
                        'notes' => $notesNovo
                    );
                } else {
                    $invoice = array(
                        'action' => 'UpdateInvoice',
                        'invoiceid' => $invoiceId,
                        'responsetype' => 'json',
                        'notes' => '__e__NFEEMITIDA'
                    );
                }
            }
        }

        $response = $this->build_query_api($invoice);

        return $response;
    }
}
