<?php



namespace Lib\I18n;

/**
 * Translator - Sistema de internacionalización (i18n)
 * 
 * Características:
 * - Múltiples locales
 * - Pluralización
 * - Interpolación de variables
 * - Fallback locale
 * - Cache de traducciones
 * 
 * @package Lib\I18n
 * @version 1.0.0
 */
class Translator
{
    private string $locale;
    private string $fallbackLocale;
    private string $translationsPath;
    private array $translations = [];
    private static ?self $instance = null;

    /**
     * Constructor
     * 
     * @param string $locale Locale actual
     * @param string $fallbackLocale Locale de respaldo
     * @param string $translationsPath Ruta a archivos de traducción
     */
    private function __construct(
        string $locale = 'es',
        string $fallbackLocale = 'en',
        string $translationsPath = ''
    ) {
        $this->locale = $locale;
        $this->fallbackLocale = $fallbackLocale;
        $this->translationsPath = $translationsPath ?: __DIR__ . '/../../lang';
    }

    /**
     * Obtiene instancia singleton
     * 
     * @param string|null $locale
     * @param string|null $fallbackLocale
     * @param string|null $translationsPath
     * @return self
     */
    public static function getInstance(
        ?string $locale = null,
        ?string $fallbackLocale = null,
        ?string $translationsPath = null
    ): self {
        if (self::$instance === null) {
            self::$instance = new self(
                $locale ?? 'es',
                $fallbackLocale ?? 'en',
                $translationsPath ?? ''
            );
        }

        return self::$instance;
    }

    /**
     * Establece locale
     * 
     * @param string $locale
     * @return void
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
        $this->loadTranslations($locale);
    }

    /**
     * Obtiene locale actual
     * 
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Traduce una key
     * 
     * @param string $key Key en formato 'archivo.key.subkey'
     * @param array $replace Variables a reemplazar
     * @param string|null $locale Locale específico
     * @return string
     */
    public function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;

        // Cargar traducciones si no están cargadas
        if (!isset($this->translations[$locale])) {
            $this->loadTranslations($locale);
        }

        // Buscar traducción
        $translation = $this->findTranslation($key, $locale);

        // Fallback si no existe
        if ($translation === null && $locale !== $this->fallbackLocale) {
            if (!isset($this->translations[$this->fallbackLocale])) {
                $this->loadTranslations($this->fallbackLocale);
            }
            $translation = $this->findTranslation($key, $this->fallbackLocale);
        }

        // Si aún no existe, retornar la key
        if ($translation === null) {
            return $key;
        }

        // Reemplazar variables
        return $this->makeReplacements($translation, $replace);
    }

    /**
     * Traduce con pluralización
     * 
     * @param string $key
     * @param int $count
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    public function transChoice(string $key, int $count, array $replace = [], ?string $locale = null): string
    {
        $translation = $this->trans($key, $replace, $locale);

        // Si la traducción contiene pluralización
        if (str_contains($translation, '|')) {
            $parts = explode('|', $translation);

            // Reglas de pluralización simples
            if ($count === 0 && isset($parts[0])) {
                $translation = trim($parts[0]);
            } elseif ($count === 1 && isset($parts[1])) {
                $translation = trim($parts[1]);
            } elseif ($count > 1 && isset($parts[2])) {
                $translation = trim($parts[2]);
            } else {
                $translation = trim($parts[count($parts) - 1]);
            }
        }

        // Agregar :count si no está en replace
        if (!isset($replace['count'])) {
            $replace['count'] = $count;
        }

        return $this->makeReplacements($translation, $replace);
    }

    /**
     * Verifica si existe una traducción
     * 
     * @param string $key
     * @param string|null $locale
     * @return bool
     */
    public function has(string $key, ?string $locale = null): bool
    {
        $locale = $locale ?? $this->locale;

        if (!isset($this->translations[$locale])) {
            $this->loadTranslations($locale);
        }

        return $this->findTranslation($key, $locale) !== null;
    }

    /**
     * Carga traducciones de un locale
     * 
     * @param string $locale
     * @return void
     */
    private function loadTranslations(string $locale): void
    {
        $localePath = $this->translationsPath . '/' . $locale;

        if (!is_dir($localePath)) {
            $this->translations[$locale] = [];
            return;
        }

        $files = glob($localePath . '/*.php');

        if ($files === false) {
            $this->translations[$locale] = [];
            return;
        }

        $this->translations[$locale] = [];

        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $translations = require $file;

            if (is_array($translations)) {
                $this->translations[$locale][$filename] = $translations;
            }
        }
    }

    /**
     * Busca traducción por key
     * 
     * @param string $key
     * @param string $locale
     * @return string|null
     */
    private function findTranslation(string $key, string $locale): ?string
    {
        $segments = explode('.', $key);

        $translation = $this->translations[$locale] ?? [];

        foreach ($segments as $segment) {
            if (!isset($translation[$segment])) {
                return null;
            }
            $translation = $translation[$segment];
        }

        return is_string($translation) ? $translation : null;
    }

    /**
     * Reemplaza variables en traducción
     * 
     * @param string $translation
     * @param array $replace
     * @return string
     */
    private function makeReplacements(string $translation, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $translation = str_replace(
                [':' . $key, ':' . strtoupper($key)],
                [$value, strtoupper((string)$value)],
                $translation
            );
        }

        return $translation;
    }

    /**
     * Obtiene todos los locales disponibles
     * 
     * @return array
     */
    public function getAvailableLocales(): array
    {
        $dirs = glob($this->translationsPath . '/*', GLOB_ONLYDIR);

        if ($dirs === false) {
            return [];
        }

        return array_map('basename', $dirs);
    }

    /**
     * Detecta locale del navegador
     * 
     * @return string
     */
    public static function detectBrowserLocale(): string
    {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        if (empty($acceptLanguage)) {
            return 'es';
        }

        // Parsear Accept-Language header
        $languages = [];
        
        foreach (explode(',', $acceptLanguage) as $lang) {
            $parts = explode(';', $lang);
            $locale = trim($parts[0]);
            $quality = 1.0;

            if (isset($parts[1]) && str_starts_with($parts[1], 'q=')) {
                $quality = (float)substr($parts[1], 2);
            }

            $languages[$locale] = $quality;
        }

        arsort($languages);

        // Retornar el primero (mayor calidad)
        $topLocale = array_key_first($languages);

        // Extraer solo el código de idioma (ej: 'es' de 'es-ES')
        if (str_contains($topLocale, '-')) {
            $topLocale = explode('-', $topLocale)[0];
        }

        return $topLocale;
    }
}

/**
 * Helper functions globales
 */

if (!function_exists('trans')) {
    /**
     * Traduce una key
     * 
     * @param string $key
     * @param array $replace
     * @return string
     */
    function trans(string $key, array $replace = []): string
    {
        return \Lib\I18n\Translator::getInstance()->trans($key, $replace);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * Traduce con pluralización
     * 
     * @param string $key
     * @param int $count
     * @param array $replace
     * @return string
     */
    function trans_choice(string $key, int $count, array $replace = []): string
    {
        return \Lib\I18n\Translator::getInstance()->transChoice($key, $count, $replace);
    }
}

if (!function_exists('__')) {
    /**
     * Alias corto de trans()
     * 
     * @param string $key
     * @param array $replace
     * @return string
     */
    function __(string $key, array $replace = []): string
    {
        return trans($key, $replace);
    }
}







