<?php

namespace App\Service;

use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslationService
{
    private $translator;

    public function __construct()
    {
        $this->translator = new GoogleTranslate();
    }

    public function translate(string $text, string $targetLang): string
    {
        if (empty($text)) {
            return $text;
        }

        try {
            $this->translator->setTarget($this->mapLanguage($targetLang));
            return $this->translator->translate($text);
        } catch (\Exception $e) {
            // Log error and return original text as fallback
            error_log('Translation error: ' . $e->getMessage());
            return $text;
        }
    }

    private function mapLanguage(string $lang): string
    {
        $map = [
            'en' => 'en',
            'fr' => 'fr',
            'ar' => 'ar',
        ];
        return $map[$lang] ?? 'en';
    }
}
