<?php

/**
 * KCAPTCHA
 * Copyright by Kruglov Sergei, 2006, 2007, 2008, 2011
 * www.captcha.ru, www.kruglov.ru
 */

namespace Intersvyaz\KCaptcha;

class KCaptcha
{
    /**
     * @var string
     * @warning do not change without changing font files!
     */
    protected $alphabet = "0123456789abcdefghijklmnopqrstuvwxyz";
    /**
     * @var string symbols used to draw CAPTCHA
     *
     * $allowed_symbols = "0123456789"; #digits
     * $allowed_symbols = "23456789abcdegkmnpqsuvxyz"; #alphabet without similar symbols (o=0, 1=l, i=j, t=f)
     */
    protected $allowedSymbols = "23456789abcdegikpqsvxyz";
    /**
     * @var string folder with fonts
     */
    protected $fontsdir = 'fonts';
    /**
     * @var array list name fonts to use on captcha (Default: all from $fontsdir)
     */
    protected $fonts = [];
    /**
     * @var int CAPTCHA string length
     */
    protected $length = 6;
    /**
     * @var int CAPTCHA image size (you do not need to change it, this parameters is optimal)
     */
    protected $width = 160;
    /**
     * @var int
     */
    protected $height = 80;
    /**
     * @var int symbol's vertical fluctuation amplitude
     */
    protected $fluctuationAmplitude = 8;
    /**
     * @var float white noise (0 - no white noise). Default 1/6
     */
    protected $whiteNoiseDensity = 0.17;
    /**
     * @var float black noise (0 - no white noise). Default 1/30
     */
    protected $blackNoiseDensity = 0.03;
    /**
     * @var bool increase safety by prevention of spaces between symbols
     */
    protected $noSpaces = true;
    /**
     * @var bool show credits (set to false to remove credits line. Credits adds 12 pixels to image height)
     */
    protected $showCredits = true;
    /**
     * @var string (if empty, HTTP_HOST will be shown)
     */
    protected $credits = 'www.captcha.ru';
    /**
     * @var array CAPTCHA image text colors (RGB) (Default: generate random)
     */
    protected $foregroundColor = [];
    /**
     * @var array CAPTCHA image background colors (RGB) (Default: generate random)
     */
    protected $backgroundColor = [];
    /**
     * @var int JPEG quality of CAPTCHA image (bigger is better quality, but larger file size)
     */
    protected $jpegQuality = 90;
    /**
     * @var string keystring (if empty, generate new)
     */
    protected $keystring = '';

    /**
     * @param array $params
     */
    public function __construct(array $params = [])
    {
        if (is_array($params)) {
            foreach ($params as $propertyName => $propertyValue) {
                if (property_exists($this, $propertyName)) {
                    $this->$propertyName = $propertyValue;
                }
            }
        }

        $this->setColors();
        $this->setFonts();

        if (empty($this->keystring)) {
            $this->keystring = $this->getRandomKeystring();
        }

        $img = $this->createImage();

        $this->printImage($img);
    }

    /**
     * @return array
     */
    private function createImage()
    {
        list($textWidth, $img) = $this->createTextImage();
        $img = $this->noise($img, $textWidth);
        $img = $this->waveDistortion($textWidth, $img);

        return $img;
    }

    /**
     * @return array
     */
    private function createTextImage()
    {
        do {
            $fontImage = $this->getRandomFontImage();

            $img = imagecreatetruecolor($this->width, $this->height);
            imagealphablending($img, true);
            imagefilledrectangle(
                $img,
                0, 0,
                $this->width - 1, $this->height - 1,
                imagecolorallocate($img, 255, 255, 255)
            );

            $textWidth = $this->drawText($fontImage, $img);
        } while ($textWidth >= $this->width - 10); // while not fit in canvas

        return [$textWidth, $img];
    }

    /**
     * @param $fontImage
     * @param $img
     * @return array
     */
    private function drawText($fontImage, $img)
    {
        $fontMetrics = $this->getFontMetrics($fontImage, strlen($this->alphabet));
        $fontImageHeight = imagesy($fontImage) - 1;
        $x = 1;
        $odd = mt_rand(0, 1);
        if ($odd == 0) {
            $odd = -1;
        }
        for ($i = 0; $i < $this->length; $i++) {
            $m = $fontMetrics[$this->keystring{$i}];

            $y = (($i % 2) * $this->fluctuationAmplitude - $this->fluctuationAmplitude / 2) * $odd
                + mt_rand(-round($this->fluctuationAmplitude / 3), round($this->fluctuationAmplitude / 3))
                + ($this->height - $fontImageHeight) / 2;

            if ($this->noSpaces) {
                $shift = 0;
                if ($i > 0) {
                    $shift = 10000;
                    for ($sy = 3; $sy < $fontImageHeight - 10; $sy += 1) {
                        for ($sx = $m['start'] - 1; $sx < $m['end']; $sx += 1) {
                            $rgb = imagecolorat($fontImage, $sx, $sy);
                            $opacity = $rgb >> 24;
                            if ($opacity < 127) {
                                $left = $sx - $m['start'] + $x;
                                $py = $sy + $y;
                                if ($py > $this->height) {
                                    break;
                                }
                                for ($px = min($left, $this->width - 1); $px > $left - 200 && $px >= 0; $px -= 1) {
                                    $color = imagecolorat($img, $px, $py) & 0xff;
                                    if ($color + $opacity < 170) { // 170 - threshold
                                        if ($shift > $left - $px) {
                                            $shift = $left - $px;
                                        }
                                        break;
                                    }
                                }
                                break;
                            }
                        }
                    }
                    if ($shift == 10000) {
                        $shift = mt_rand(4, 6);
                    }

                }
            } else {
                $shift = 1;
            }
            imagecopy($img, $fontImage, $x - $shift, $y, $m['start'], 1, $m['end'] - $m['start'], $fontImageHeight);
            $x += $m['end'] - $m['start'] - $shift;
        }

        return $x;
    }

    /**
     * @return string keystring
     */
    public function getKeyString()
    {
        return $this->keystring;
    }

    /**
     * generating random keystring
     */
    private function getRandomKeystring()
    {
        do {
            $keystring = '';
            for ($i = 0; $i < $this->length; $i++) {
                $keystring .= $this->allowedSymbols{mt_rand(0, strlen($this->allowedSymbols) - 1)};
            }
        } while (!preg_match('/cp|cb|ck|c6|c9|rn|rm|mm|co|do|cl|db|qp|qb|dp|ww/', $keystring));

        return $keystring;
    }

    private function setColors()
    {
        if (count($this->foregroundColor) !== 3 || count($this->backgroundColor) !== 3) {
            $this->foregroundColor = [mt_rand(0, 80), mt_rand(0, 80), mt_rand(0, 80)];
            $this->backgroundColor = [mt_rand(220, 255), mt_rand(220, 255), mt_rand(220, 255)];
        }
    }

    private function setFonts()
    {
        if ($this->fonts === []) {
            $fontsdir_absolute = __DIR__ . '/' . $this->fontsdir;
            if ($handle = opendir($fontsdir_absolute)) {
                while (false !== ($file = readdir($handle))) {
                    if (preg_match('/\.png$/i', $file)) {
                        $this->fonts[] = substr($file, 0, -4);
                    }
                }
                closedir($handle);
            }
        }
    }

    private function getRandomFontImage()
    {
        $fontName = $this->fonts[mt_rand(0, count($this->fonts) - 1)];
        $fontFile = $this->fontsdir . '/' . $fontName . '.png';
        $fontImage = imagecreatefrompng($fontFile);
        imagealphablending($fontImage, true);

        return $fontImage;
    }

    /**
     * @param $fontImage
     * @param $alphabetLength
     * @return array
     */
    private function getFontMetrics($fontImage, $alphabetLength)
    {
        $fontfile_width = imagesx($fontImage);

        $font_metrics = [];
        $symbol = 0;
        $reading_symbol = false;

        // loading font
        for ($i = 0; $i < $fontfile_width && $symbol < $alphabetLength; $i++) {
            $transparent = (imagecolorat($fontImage, $i, 0) >> 24) == 127;

            if (!$reading_symbol && !$transparent) {
                $font_metrics[$this->alphabet{$symbol}] = ['start' => $i];
                $reading_symbol = true;
                continue;
            }

            if ($reading_symbol && $transparent) {
                $font_metrics[$this->alphabet{$symbol}]['end'] = $i;
                $reading_symbol = false;
                $symbol++;
                continue;
            }
        }

        return $font_metrics;
    }

    /**
     * @param $image
     */
    private function printImage($image)
    {
        if (function_exists("imagejpeg")) {
            header("Content-Type: image/jpeg");
            imagejpeg($image, './test.jpg', $this->jpegQuality);
            imagejpeg($image, null, $this->jpegQuality);
        } elseif (function_exists("imagegif")) {
            header("Content-Type: image/gif");
            imagegif($image);
        } elseif (function_exists("imagepng")) {
            header("Content-Type: image/x-png");
            imagepng($image);
        }
    }

    /**
     * @param $img
     * @param $textWidth
     */
    private function noise($img, $textWidth)
    {
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        for ($i = 0; $i < (($this->height - 30) * $textWidth) * $this->whiteNoiseDensity; $i++) {
            imagesetpixel($img, mt_rand(0, $textWidth - 1), mt_rand(10, $this->height - 15), $white);
        }
        for ($i = 0; $i < (($this->height - 30) * $textWidth) * $this->blackNoiseDensity; $i++) {
            imagesetpixel($img, mt_rand(0, $textWidth - 1), mt_rand(10, $this->height - 15), $black);
        }

        return $img;
    }

    /**
     * @param $textWidth
     * @param $img
     * @return resource
     */
    private function waveDistortion($textWidth, $img)
    {
        $center = $textWidth / 2;

        // credits. To remove, see configuration file
        $img2 = imagecreatetruecolor($this->width, $this->height);
        $foreground = imagecolorallocate($img2, $this->foregroundColor[0], $this->foregroundColor[1],
            $this->foregroundColor[2]);
        $background = imagecolorallocate($img2, $this->backgroundColor[0], $this->backgroundColor[1],
            $this->backgroundColor[2]);
        imagefilledrectangle($img2, 0, 0, $this->width - 1, $this->height - 1, $background);

        // periods
        $rand1 = mt_rand(750000, 1200000) / 10000000;
        $rand2 = mt_rand(750000, 1200000) / 10000000;
        $rand3 = mt_rand(750000, 1200000) / 10000000;
        $rand4 = mt_rand(750000, 1200000) / 10000000;
        // phases
        $rand5 = mt_rand(0, 31415926) / 10000000;
        $rand6 = mt_rand(0, 31415926) / 10000000;
        $rand7 = mt_rand(0, 31415926) / 10000000;
        $rand8 = mt_rand(0, 31415926) / 10000000;
        // amplitudes
        $rand9 = mt_rand(330, 420) / 110;
        $rand10 = mt_rand(330, 450) / 100;

        //wave distortion

        for ($x = 0; $x < $this->width; $x++) {
            for ($y = 0; $y < $this->height; $y++) {
                $sx = $x + (sin($x * $rand1 + $rand5) + sin($y * $rand3 + $rand6)) * $rand9 - $this->width / 2 + $center + 1;
                $sy = $y + (sin($x * $rand2 + $rand7) + sin($y * $rand4 + $rand8)) * $rand10;

                if ($sx < 0 || $sy < 0 || $sx >= $this->width - 1 || $sy >= $this->height - 1) {
                    continue;
                } else {
                    $color = imagecolorat($img, $sx, $sy) & 0xFF;
                    $color_x = imagecolorat($img, $sx + 1, $sy) & 0xFF;
                    $color_y = imagecolorat($img, $sx, $sy + 1) & 0xFF;
                    $color_xy = imagecolorat($img, $sx + 1, $sy + 1) & 0xFF;
                }

                if ($color == 255 && $color_x == 255 && $color_y == 255 && $color_xy == 255) {
                    continue;
                } elseif ($color == 0 && $color_x == 0 && $color_y == 0 && $color_xy == 0) {
                    $newred = $this->foregroundColor[0];
                    $newgreen = $this->foregroundColor[1];
                    $newblue = $this->foregroundColor[2];
                } else {
                    $frsx = $sx - floor($sx);
                    $frsy = $sy - floor($sy);
                    $frsx1 = 1 - $frsx;
                    $frsy1 = 1 - $frsy;

                    $newcolor = (
                        $color * $frsx1 * $frsy1 +
                        $color_x * $frsx * $frsy1 +
                        $color_y * $frsx1 * $frsy +
                        $color_xy * $frsx * $frsy);

                    if ($newcolor > 255) {
                        $newcolor = 255;
                    }
                    $newcolor = $newcolor / 255;
                    $newcolor0 = 1 - $newcolor;

                    $newred = $newcolor0 * $this->foregroundColor[0] + $newcolor * $this->backgroundColor[0];
                    $newgreen = $newcolor0 * $this->foregroundColor[1] + $newcolor * $this->backgroundColor[1];
                    $newblue = $newcolor0 * $this->foregroundColor[2] + $newcolor * $this->backgroundColor[2];
                }

                imagesetpixel($img2, $x, $y, imagecolorallocate($img2, $newred, $newgreen, $newblue));
            }
        }

        return $img2;
    }
}
