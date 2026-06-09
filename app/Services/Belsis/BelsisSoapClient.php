<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BelsisSoapClient
{
    private string $namespace;

    public function __construct()
    {
        $this->namespace = config('belsis.namespace');
    }

    /**
     * @param  array<string, mixed>  $girdiParametre
     * @return array<string, mixed>
     */
    public function callTahakkuk(string $method, array $girdiParametre = [], bool $wrapGirdiParametre = true): array
    {
        return $this->call(config('belsis.tahakkuk_url'), $method, $girdiParametre, $wrapGirdiParametre);
    }

    /**
     * @param  array<string, mixed>  $girdiParametre
     * @return array<string, mixed>
     */
    public function callTahsilat(string $method, array $girdiParametre = []): array
    {
        return $this->call(config('belsis.tahsilat_url'), $method, $girdiParametre);
    }

    /**
     * @param  array<string, mixed>  $girdiParametre
     * @return array<string, mixed>
     */
    public function call(string $endpoint, string $method, array $girdiParametre = [], bool $wrapGirdiParametre = true): array
    {
        $body = $this->buildEnvelope($method, $girdiParametre, $wrapGirdiParametre);
        $soapAction = $this->namespace.$method;

        Log::debug('Belsis SOAP request', ['method' => $method, 'endpoint' => $endpoint]);

        $request = Http::timeout(config('belsis.timeout'))
            ->withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction'   => '"'.$soapAction.'"',
            ])
            ->withBody($body, 'text/xml');

        if (! config('belsis.verify_ssl')) {
            $request = $request->withoutVerifying();
        }

        $response = $request->post($endpoint);

        if (! $response->successful()) {
            throw new BelsisException(
                'Belsis servisine bağlanılamadı. HTTP '.$response->status(),
            );
        }

        $raw = $response->body();

        if (str_contains($raw, '<html') || str_contains($raw, 'loginform')) {
            throw new BelsisException('Belsis servisi kimlik doğrulama sayfası döndürdü. IP erişimi veya URL kontrol edin.');
        }

        return $this->parseResponse($raw, $method);
    }

    /**
     * @param  array<string, mixed>  $girdiParametre
     */
    private function buildEnvelope(string $method, array $girdiParametre, bool $wrapGirdiParametre = true): string
    {
        $paramsXml = $this->buildParamsXml($girdiParametre);

        $methodBody = $wrapGirdiParametre
            ? '<tem:'.$method.'><tem:girdiParametre>'.$paramsXml.'</tem:girdiParametre></tem:'.$method.'>'
            : '<tem:'.$method.'>'.$paramsXml.'</tem:'.$method.'>';

        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="'.$this->namespace.'">'
            .'<soapenv:Header/>'
            .'<soapenv:Body>'
            .$methodBody
            .'</soapenv:Body>'
            .'</soapenv:Envelope>';
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function buildParamsXml(array $params): string
    {
        $xml = '';

        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $xml .= '<tem:'.$key.'>'.$this->buildParamsXml($value).'</tem:'.$key.'>';
            } else {
                $xml .= '<tem:'.$key.'>'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</tem:'.$key.'>';
            }
        }

        return $xml;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseResponse(string $xml, string $method): array
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument;
        if (! $doc->loadXML($xml)) {
            throw new BelsisException('Belsis yanıtı XML olarak çözümlenemedi.');
        }

        $resultKey = $method.'Result';
        $nodes = $doc->getElementsByTagName($resultKey);

        if ($nodes->length === 0) {
            $fault = $doc->getElementsByTagName('faultstring');
            if ($fault->length > 0) {
                throw new BelsisException(trim($fault->item(0)->textContent));
            }
            throw new BelsisException('Belsis yanıtında '.$resultKey.' bulunamadı.');
        }

        $result = $this->domNodeToArray($nodes->item(0));

        if (isset($result['sonucKodu']) && (string) $result['sonucKodu'] !== '1001') {
            throw new BelsisException(
                $result['sonucAciklama'] ?? $result['mesaj'] ?? 'Belsis işlemi başarısız.',
                (string) $result['sonucKodu'],
            );
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|string
     */
    private function domNodeToArray(\DOMNode $node): array|string
    {
        if (! $node->hasChildNodes()) {
            return '';
        }

        $output = [];
        $textOnly = true;

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $text = trim($child->textContent);
                if ($text !== '') {
                    return $text;
                }
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $textOnly = false;
            $name = $child->localName ?: $child->nodeName;
            $value = $this->domNodeToArray($child);

            if (isset($output[$name])) {
                if (! is_array($output[$name]) || ! array_is_list($output[$name])) {
                    $output[$name] = [$output[$name]];
                }
                $output[$name][] = $value;
            } else {
                $output[$name] = $value;
            }
        }

        if ($textOnly) {
            return trim($node->textContent);
        }

        return $output;
    }
}
