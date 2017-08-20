<?php

require 'vendor/autoload.php';


use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\DomCrawler\Crawler;


function crawler($page = 1, $rows = 1)
{
    $url = "http://emec.mec.gov.br";
    $jar = new CookieJar();
    $client = new Client();
    $response = $client->request('GET', $url, ['cookies' => $jar, 'allow_redirects' => true]);

    $arPostData = [
        "data" => [
            "CONSULTA_AVANCADA" => [
                "hid_template" => "listar-consulta-avancada-ies",
                "hid_order" => "ies.no_ies ASC",
                "hid_no_cidade_avancada" => "",
                "hid_no_regiao_avancada" => "",
                "hid_no_pais_avancada" => "",
                "hid_co_pais_avancada" => "",
                "rad_buscar_por" => "IES",
                "txt_no_ies" => "",
                "txt_no_ies_curso" => "",
                "txt_no_curso" => "",
                "sel_co_area_geral" => "",
                "sel_co_area_especifica" => "",
                "sel_co_area_detalhada" => "",
                "sel_co_area_curso" => "",
                "txt_no_especializacao" => "",
                "sel_co_area" => "",
                "sel_sg_uf" => "SP",
                //"sel_co_municipio" => "000000004314902", //POA
                "sel_co_municipio" => "000000003550308", //SP
                "sel_st_gratuito" => "",
                "sel_no_indice_ies" => "",
                "sel_co_indice_ies" => "",
                "sel_no_indice_curso" => "",
                "sel_co_indice_curso" => "",
                "sel_co_situacao_funcionamento_ies" => "10035",
                "sel_co_situacao_funcionamento_curso" => "10056", //Em Atividade
                "sel_st_funcionamento_especializacao" => "",
            ],
        ],
        "captcha" => "",
    ];
    //Ex: $page = 2, $rows = 5, retorna 5 registros por vez, neste caso regsitro de 6 a 10
    $url = "http://emec.mec.gov.br/emec/nova-index/listar-consulta-avancada/page/{$page}/list/{$rows}";

    $response = $client->request('POST', $url, ['cookies' => $jar,
        'allow_redirects' => [
            'max' => 10,
            'strict' => true,
            'referer' => true,
            'protocols' => ['http'],
            'on_redirect' => 'http://emec.mec.gov.br/emec/nova',
            'track_redirects' => true,
        ],
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:45.0) Gecko/20100101 Firefox/45.0',
            'Accept' => 'text/html, */*',
            'Accept-Encoding' => 'gzip, deflate',
            'Accept-Language' => 'pt-BR,pt;q=0.8,en-US;q=0.6,en;q=0.4,es;q=0.2',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Origin' => 'http://emec.mec.gov.br',
            'X-Requested-With' => 'XMLHttpRequest',
        ],
        'form_params' => $arPostData,
        'stream' => true,
        'decode_content' => 'gzip']);

    // crate crawler instance from body HTML code
    $crawler = new Crawler($response->getBody()->getContents());

    // aplica filtro para buscar dados do onclick de cada resgistro
    $idsIes = $crawler->filter('#tbyDados tr')->each(function ($node, $i) {
        //busca id na função do onclick
        preg_match("/\(([^)]+)\)/", $node->attr('onclick'), $output_array);
        if (isset($output_array[1])) {
            return base64_encode(trim($output_array[1]));
        }
    });

    $dadosInstituicoes = [];
    foreach (array_unique($idsIes) as $idIe) {

        $dadosInstituicoes[] = getDetalheIES($idIe);

    }
    print_r('<pre>');
    print_r($dadosInstituicoes);
}


/**
 * @description pega dados da instituição
 * @param $idsIes código da instituição
 */
function getDetalheIES($idIe)
{

    $client = new Client();

    $url = "http://emec.mec.gov.br/emec/consulta-ies/index/d96957f455f6405d14c6542552b0f6eb/{$idIe}";

    $response = $client->request('GET', $url);
    // crate crawler instance from body HTML code
    $crawler = new Crawler($response->getBody()->getContents());
    $htmlInstituicao = $crawler->filter('.avalTabCampos > .avalLinhaCampos');

    $dadosInstituicao = $htmlInstituicao->filterXPath('//td[contains(@class, "subline2") and not(contains(@class, "tituloCampos"))]')->each(function ($node, $i) {
        return trim($node->text());
    });

    preg_match("/\(([^)]+)\)/", $dadosInstituicao[0], $codigo);

    $instituicao = [
        "codigo" => $codigo[1] ?? "",
        "nome" => $dadosInstituicao[0] ?? "",
        "endereco" => $dadosInstituicao[2] ?? "",
        "numero" => $dadosInstituicao[3] ?? "",
        "complemento" => $dadosInstituicao[4] ?? "",
        "cep" => $dadosInstituicao[5] ?? "",
        "bairro" => $dadosInstituicao[6] ?? "",
        "municipio" => $dadosInstituicao[7] ?? "",
        "uf" => $dadosInstituicao[8] ?? "",
        "telefone" => $dadosInstituicao[9] ?? "",
        "fax" => $dadosInstituicao[10] ?? "",
        "organizacao_academica" => $dadosInstituicao[11] ?? "",
        "site" => $dadosInstituicao[12] ?? "",
        "categoria_administrativa" => $dadosInstituicao[13] ?? "",
        "email" => $dadosInstituicao[14] ?? "",
        "reitor_dirigente" => $dadosInstituicao[18] ?? "",
    ];

    $htmlIndices = $crawler->filter('#listar-ies-cadastro tbody tr td')->each(function ($node, $i) {
        return trim($node->text());
    });

    $indices = [
        "ci_conceito_institucional" => [
            "valor" => $htmlIndices[1],
            "ano" => $htmlIndices[2],
        ],
        "igc_indice_geral_cursos" => [
            "valor" => $htmlIndices[4],
            "ano" => $htmlIndices[5],
        ],
        "igc_continuo" => [
            "valor" => $htmlIndices[7],
            "ano" => $htmlIndices[8],
        ],
    ];
    return array_merge($instituicao, $indices);
}

crawler();
