<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BelsisSoapClient
{
    private string $namespace;

    /** @var array<int, string> */
    private array $successCodes = ['0', '1001'];

    public function __construct()
    {
        $this->namespace = config('belsis.namespace');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function callTahakkuk(string $method, array $params = [], string $wrapper = 'girdiParametre'): array
    {
        return $this->call(config('belsis.tahakkuk_url'), $method, $params, $wrapper);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function callTahsilat(string $method, array $params = [], string $wrapper = 'girdiParametre'): array
    {
        return $this->call(config('belsis.tahsilat_url'), $method, $params, $wrapper);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function call(string $endpoint, string $method, array $params = [], string $wrapper = 'girdiParametre'): array
    {
        $lastError = null;

        foreach ($this->endpointCandidates($endpoint) as $candidate) {
            try {
                return $this->sendRequest($candidate, $method, $params, $wrapper);
            } catch (BelsisException $e) {
                $lastError = $e;
                if ($this->isIpAuthorizationError($e->getMessage()) || $this->isUnreachableHostError($e->getMessage())) {
                    throw $e;
                }
            }
        }

        throw $lastError ?? new BelsisException('Belsis servisine bağlanılamadı.');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function sendRequest(string $endpoint, string $method, array $params, string $wrapper): array
    {
        $body = $this->buildEnvelope($method, $params, $wrapper);
        $soapAction = $this->namespace.$method;

        Log::debug('Belsis SOAP request', ['method' => $method, 'endpoint' => $endpoint]);

        $request = Http::withOptions(['allow_redirects' => false])
            ->connectTimeout((int) min(5, max(1, (int) config('belsis.timeout', 30))))
            ->timeout((int) config('belsis.timeout', 30))
            ->withHeaders([
                'Content-Type'   => 'text/xml; charset=utf-8',
                'Content-Length' => (string) strlen($body),
                'SOAPAction'     => '"'.$soapAction.'"',
            ])
            ->withBody($body, 'text/xml');

        if (! config('belsis.verify_ssl')) {
            $request = $request->withoutVerifying();
        }

        try {
            $response = $request->post($endpoint);
        } catch (ConnectionException $e) {
            $message = $this->explainConnectionError($e, $endpoint);
            Log::warning('Belsis SOAP connection error', [
                'method'   => $method,
                'endpoint' => $endpoint,
                'message'  => $message,
            ]);
            throw new BelsisException($message);
        } catch (Throwable $e) {
            if ($e instanceof BelsisException) {
                throw $e;
            }

            $message = 'Belsis servisine bağlanılamadı: '.$e->getMessage();
            Log::warning('Belsis SOAP client error', [
                'method'   => $method,
                'endpoint' => $endpoint,
                'message'  => $message,
            ]);
            throw new BelsisException($message);
        }

        $raw = $response->body();
        $status = $response->status();

        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            $message = $this->explainRedirect($response->header('Location') ?? '', $endpoint);
            Log::warning('Belsis SOAP transport error', ['method' => $method, 'status' => $status, 'message' => $message]);
            throw new BelsisException($message);
        }

        if (! $response->successful()) {
            if ($this->looksLikeHtml($raw)) {
                $message = $this->explainHtmlResponse($raw, $endpoint);
                Log::warning('Belsis SOAP transport error', ['method' => $method, 'status' => $status, 'message' => $message]);
                throw new BelsisException($message);
            }
            Log::warning('Belsis SOAP transport error', ['method' => $method, 'status' => $status, 'message' => 'HTTP '.$status]);
            throw new BelsisException('Belsis servisine bağlanılamadı. HTTP '.$status);
        }

        if ($this->looksLikeHtml($raw)) {
            $message = $this->explainHtmlResponse($raw, $endpoint);
            Log::warning('Belsis SOAP transport error', ['method' => $method, 'status' => $status, 'message' => $message]);
            throw new BelsisException($message);
        }

        return $this->parseResponse($raw, $method);
    }

    /**
     * @return array<int, string>
     */
    private function endpointCandidates(string $endpoint): array
    {
        $candidates = [$endpoint];
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));

        // İç DNS (.local) veya açıkça HTTP verilen lokal kurulumlarda HTTPS denemesi
        // sadece zaman kaybettirir; DNS yoksa fatal max_execution_time'a gider.
        $isLocalHost = $host === 'localhost'
            || str_ends_with($host, '.local')
            || filter_var($host, FILTER_VALIDATE_IP) !== false;

        if (! $isLocalHost && str_starts_with($endpoint, 'http://')) {
            $candidates[] = 'https://'.substr($endpoint, 7);
        }

        return array_values(array_unique($candidates));
    }

    private function isUnreachableHostError(string $message): bool
    {
        return str_contains($message, 'DNS:')
            || str_contains($message, 'ulaşılamadı')
            || str_contains($message, 'bağlanılamadı')
            || str_contains($message, 'baglanilamadi')
            || str_contains($message, 'zaman aşımı')
            || str_contains($message, 'Could not resolve host');
    }

    private function looksLikeHtml(string $raw): bool
    {
        $sample = strtolower(substr(ltrim($raw), 0, 200));

        return str_contains($sample, '<html')
            || str_contains($sample, '<!doctype')
            || str_contains($sample, 'loginform')
            || str_contains($sample, 'length required');
    }

    private function explainConnectionError(ConnectionException $e, string $endpoint): string
    {
        $detail = $e->getMessage();
        $host = parse_url($endpoint, PHP_URL_HOST) ?: $endpoint;

        if (str_contains($detail, 'Could not resolve host') || str_contains($detail, 'getaddrinfo')) {
            return 'Belsis sunucusuna ulaşılamadı (DNS: '.$host.'). '
                .'Kiosk uygulamasının belediye iç ağında çalıştığından ve '
                .'BELSIS_TAHSILAT_URL / BELSIS_TAHAKKUK_URL adreslerinin doğru olduğundan emin olun.';
        }

        if (str_contains($detail, 'Connection timed out') || str_contains($detail, 'timed out')) {
            return 'Belsis sunucusuna zaman aşımı ('.$host.'). '
                .'Ağ bağlantısını ve port erişimini kontrol ediniz.';
        }

        if (str_contains($detail, 'Failed to connect') || str_contains($detail, 'Connection refused')) {
            return 'Belsis sunucusuna bağlanılamadı ('.$host.'). '
                .'Servis kapalı olabilir veya güvenlik duvarı engelliyor olabilir.';
        }

        return 'Belsis servisine bağlanılamadı ('.$host.'): '.$detail;
    }

    private function explainRedirect(string $location, string $endpoint): string
    {
        if (str_contains($location, 'yetkisiz_ip')) {
            return 'Belsis sunucusu bu makinenin IP adresini tanımıyor (yetkisiz_ip). '
                .'Kiosk sunucunuzun IP adresini Belsis/belediye IT\'ye bildirin. '
                .'Tespit: '.BelsisIpResolver::detect();
        }

        return 'Belsis isteği yönlendirildi ('.$location.'). '
            .'.env içinde HTTPS URL kullanın: '.$this->suggestHttpsUrl($endpoint);
    }

    private function explainHtmlResponse(string $raw, string $endpoint): string
    {
        if (str_contains(strtolower($raw), 'length required')) {
            return 'Belsis sunucusu HTTP 411 döndürdü. Content-Length düzeltmesi uygulandı; '
                .'php artisan config:clear sonrası tekrar deneyin.';
        }

        if (str_contains(strtolower($raw), 'yetkisiz_ip')) {
            return 'Belsis IP yetkisi yok. Sunucu IP: '.BelsisIpResolver::detect();
        }

        return 'Belsis servisi SOAP yerine HTML sayfası döndürdü. '
            .'URL: '.$endpoint.' | Sunucu IP: '.BelsisIpResolver::detect().' '
            .'— Belsis IT\'den bu IP için aykome web servis erişimi isteyin.';
    }

    private function suggestHttpsUrl(string $endpoint): string
    {
        return str_starts_with($endpoint, 'http://')
            ? 'https://'.substr($endpoint, 7)
            : $endpoint;
    }

    private function isIpAuthorizationError(string $message): bool
    {
        return str_contains($message, 'yetkisiz_ip')
            || str_contains($message, 'IP adresini tanımıyor')
            || str_contains($message, 'IP yetkisi yok');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function buildEnvelope(string $method, array $params, string $wrapper): string
    {
        $paramsXml = $this->buildParamsXml($params);

        $inner = $wrapper === ''
            ? $paramsXml
            : '<tem:'.$wrapper.'>'.$paramsXml.'</tem:'.$wrapper.'>';

        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="'.$this->namespace.'">'
            .'<soapenv:Header/><soapenv:Body>'
            .'<tem:'.$method.'>'.$inner.'</tem:'.$method.'>'
            .'</soapenv:Body></soapenv:Envelope>';
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

            if (is_array($value) && isset($value['__empty']) && $value['__empty'] === true) {
                $xml .= '<tem:'.$key.' />';
                continue;
            }

            if (is_array($value) && isset($value['__list'])) {
                $itemTag = (string) $value['__list'];
                if (empty($value['__items'])) {
                    $xml .= '<tem:'.$key.' />';
                    continue;
                }
                foreach ($value['__items'] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $xml .= '<tem:'.$key.'><tem:'.$itemTag.'>'.$this->buildParamsXml($item).'</tem:'.$itemTag.'></tem:'.$key.'>';
                }
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

        if (! is_array($result)) {
            return [];
        }

        if (isset($result['sonucKodu']) && ! in_array((string) $result['sonucKodu'], $this->successCodes, true)) {
            $code = (string) $result['sonucKodu'];

            if ($this->isSoftSuccess($method, $code)) {
                return $result;
            }

            $message = $result['sonucAciklamasi']
                ?? $result['sonucAciklama']
                ?? $result['hataMesaji']
                ?? $result['mesaj']
                ?? 'Belsis işlemi başarısız (kod: '.$code.').';

            if (! empty($result['hataMesaji']) && $result['hataMesaji'] !== $message) {
                $message .= ' — '.$result['hataMesaji'];
            }

            Log::warning('Belsis SOAP business error', ['method' => $method, 'sonucKodu' => $result['sonucKodu'], 'message' => $message]);

            throw new BelsisException((string) $message, (string) $result['sonucKodu']);
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

    private function isSoftSuccess(string $method, string $code): bool
    {
        $methods = config('belsis.soft_success_methods', ['borcSorgula', 'arama']);
        $codes = config('belsis.soft_success_codes', ['1004']);

        return in_array($method, $methods, true) && in_array($code, $codes, true);
    }
}
