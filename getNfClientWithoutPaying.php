<?php

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__FILE__));
$dotenv->load();

function consultarClientes(): array
{

    $userIdsCheckNFSemBoleto = [];
    // Configurações de conexão com o banco de dados
    $host = $_ENV['HOST'];
    $database = $_ENV['DATABASE'];
    $username = $_ENV['USER'];
    $password = $_ENV['PASSWORD'];

    // Conectando ao banco de dados
    $mysqli = new mysqli($host, $username, $password, $database);

    // Verificando a conexão
    if ($mysqli->connect_errno) {
        die('Falha na conexão com o banco de dados: ' . $mysqli->connect_error);
    }

    // Consulta SQL
    $query = "SELECT * FROM tblinvoices
        WHERE tblinvoices.userid IN (
            SELECT tblclients.id
            FROM tblclients
            JOIN tblcustomfieldsvalues ON tblclients.id = tblcustomfieldsvalues.relid
            WHERE tblcustomfieldsvalues.fieldid = 124 AND tblcustomfieldsvalues.value = 'on'
        )
    AND tblinvoices.status = 'Unpaid' 
    AND tblinvoices.notes NOT REGEXP '__e__NFEEMITIDA|__e__PROBLEMA$'";

    // Executando a consulta
    if ($result = $mysqli->query($query)) {
        // Verificando se há registros retornados
        if ($result->num_rows > 0) {
            // Percorrendo os registros retornados
            while ($row = $result->fetch_assoc()) {
                // Faça algo com cada registro retornado
                array_push($userIdsCheckNFSemBoleto, $row['id']);
            }
        } else {
            return ['Nenhum registro encontrado.'];
        }

        // Liberando os recursos do resultado da consulta
        $result->close();
    } else {
        echo 'Erro ao executar a consulta: ' . $mysqli->error;
    }

    // Fechando a conexão
    $mysqli->close();

    return $userIdsCheckNFSemBoleto;
}
