<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class DocxProcessor
{
    public function extractText($filePath)
    {
        try {
            // Load the DOCX file
            $phpWord = IOFactory::load($filePath);
            
            // Initialize text content
            $text = '';
            
            // Extract text from each section
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . "\n";
                    }
                }
            }
            
            return trim($text);
        } catch (\Exception $e) {
            throw new \Exception('Error processing DOCX file: ' . $e->getMessage());
        }
    }
} 