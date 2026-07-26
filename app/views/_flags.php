<?php
// Flaggen als Inline-SVG (Windows stellt Flaggen-Emojis nicht dar)
function flag_svg(string $code): string {
  $svg = fn(string $inner): string => '<svg class="flag" viewBox="0 0 60 40" aria-hidden="true">' . $inner . '</svg>';
  return match ($code) {
    'de' => $svg('<rect width="60" height="40" fill="#000"/><rect y="13.33" width="60" height="13.33" fill="#DD0000"/><rect y="26.66" width="60" height="13.34" fill="#FFCE00"/>'),
    'en' => $svg('<rect width="60" height="40" fill="#012169"/><path d="M0,0 60,40 M60,0 0,40" stroke="#fff" stroke-width="8"/><path d="M0,0 60,40 M60,0 0,40" stroke="#C8102E" stroke-width="4"/><path d="M30,0 V40 M0,20 H60" stroke="#fff" stroke-width="13"/><path d="M30,0 V40 M0,20 H60" stroke="#C8102E" stroke-width="8"/>'),
    'nl' => $svg('<rect width="60" height="40" fill="#21468B"/><rect width="60" height="26.66" fill="#fff"/><rect width="60" height="13.33" fill="#AE1C28"/>'),
    'fr' => $svg('<rect width="60" height="40" fill="#ED2939"/><rect width="40" height="40" fill="#fff"/><rect width="20" height="40" fill="#002395"/>'),
    'es' => $svg('<rect width="60" height="40" fill="#AA151B"/><rect y="10" width="60" height="20" fill="#F1BF00"/>'),
    'it' => $svg('<rect width="60" height="40" fill="#CE2B37"/><rect width="40" height="40" fill="#fff"/><rect width="20" height="40" fill="#009246"/>'),
    default => '',
  };
}
