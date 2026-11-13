<?php

namespace Demo;

/**
 * Environment settings (private/.env, overridden by real environment variables) and the
 * catalog (private/catalog.php) of issuers and credentials that the front-end may use.
 */
final class Config
{
    /**
     * @param array<string,string> $env
     * @param array{issuers:array<string,array>,credentials:array<string,array>} $catalog
     */
    public function __construct(private array $env, private array $catalog)
    {
    }

    public static function load(string $privateDir): self
    {
        $env = self::parseEnvFile($privateDir . '/.env');
        foreach (getenv() as $key => $value) {
            if (is_string($value) && (array_key_exists($key, $env) || preg_match('/^(ISSUER|VERIFIER|DEMO)_/', $key))) {
                $env[$key] = $value;
            }
        }
        $factory = require $privateDir . '/catalog.php';
        return new self($env, $factory($env));
    }

    /** @return array<string,string> */
    public static function parseEnvFile(string $file): array
    {
        $env = [];
        $lines = is_readable($file) ? file($file, FILE_IGNORE_NEW_LINES) : [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            $env[trim($key)] = $value;
        }
        return $env;
    }

    public function bool(string $key, bool $default): bool
    {
        if (!isset($this->env[$key])) {
            return $default;
        }
        return in_array(strtolower($this->env[$key]), ['1', 'true', 'yes', 'on'], true);
    }

    /** @return array{key:string,name:string,baseUrl:string,token:string} */
    public function issuer(string $key): array
    {
        $issuer = $this->catalog['issuers'][$key] ?? null;
        if ($issuer === null) {
            throw new ApiException(500, 'configuration_error', "Unknown issuer '$key'");
        }
        $issuer['key'] = $key;
        $issuer['baseUrl'] = rtrim($issuer['baseUrl'], '/');
        return $issuer;
    }

    /** @return array<string,mixed> */
    public function credential(string $key): array
    {
        $credential = $this->catalog['credentials'][$key] ?? null;
        if ($credential === null) {
            throw new ApiException(422, 'unknown_credential', 'Unknown credential');
        }
        $credential['key'] = $key;
        return $credential;
    }

    /**
     * A bundle: credentials that are issued in one offer.
     * @return array{key:string,title:string,issuer:string,credentials:list<string>}
     */
    public function bundle(string $key): array
    {
        $bundle = $this->catalog['bundles'][$key] ?? null;
        if ($bundle === null) {
            throw new ApiException(422, 'unknown_bundle', 'Unknown bundle');
        }
        $bundle['key'] = $key;
        return $bundle;
    }

    /** The part of the catalog that is safe to show the front-end: no tokens, no upstream URLs. */
    public function publicCatalog(): array
    {
        $issuers = [];
        foreach ($this->catalog['issuers'] as $key => $issuer) {
            $issuers[$key] = ['name' => $issuer['name'], 'configured' => ($issuer['token'] ?? '') !== ''];
        }
        $credentials = [];
        foreach ($this->catalog['credentials'] as $key => $credential) {
            $fields = [];
            foreach ($credential['fields'] as $name => $field) {
                $fields[] = ['name' => $name] + $field;
            }
            $credentials[$key] = [
                'title' => $credential['title'],
                'issuer' => $credential['issuer'],
                'deferred' => $credential['deferred'] ?? false,
                'fields' => $fields,
            ];
        }
        $bundles = [];
        foreach ($this->catalog['bundles'] ?? [] as $key => $bundle) {
            $bundles[$key] = ['title' => $bundle['title'], 'issuer' => $bundle['issuer'], 'credentials' => $bundle['credentials']];
        }
        return ['environment' => 'dev', 'issuers' => $issuers, 'credentials' => $credentials, 'bundles' => $bundles];
    }
}
