<?php

declare(strict_types=1);

/**
 * Fluent, immutable builder for a single QR code.
 *
 * Every setter returns a new builder, so a configured builder can be reused as a
 * template without later calls mutating it. Terminal methods (`encode`,
 * `toMatrix`, `toSvg`, `toPng`, `toDataUri`) run the encoder and the chosen
 * renderer and return the result.
 *
 *     $svg = $factory->create('https://phpdot.com')
 *         ->errorCorrection(ErrorCorrection::High)
 *         ->size(400)
 *         ->foreground(Color::fromHex('#0b5'))
 *         ->toSvg();
 *
 * Obtain instances from {@see QrCodeFactory}; the renderers and encoder are
 * injected for you.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode;

use PHPdot\QrCode\Encoder\Encoder;
use PHPdot\QrCode\Enum\ErrorCorrection;
use PHPdot\QrCode\Enum\ImageFormat;
use PHPdot\QrCode\Renderer\DataUriRenderer;
use PHPdot\QrCode\Renderer\PngRenderer;
use PHPdot\QrCode\Renderer\SvgRenderer;

final readonly class QrCodeBuilder
{
    public function __construct(
        private Encoder $encoder,
        private SvgRenderer $svg,
        private PngRenderer $png,
        private DataUriRenderer $dataUri,
        private string $data = '',
        private ErrorCorrection $level = ErrorCorrection::Medium,
        private string $encoding = Encoder::DEFAULT_ENCODING,
        private int|null $forceVersion = null,
        private bool $eci = true,
        private RenderOptions $options = new RenderOptions(),
    ) {}

    public function data(string $data): self
    {
        return $this->with(data: $data);
    }

    public function errorCorrection(ErrorCorrection $level): self
    {
        return $this->with(level: $level);
    }

    public function encoding(string $encoding): self
    {
        return $this->with(encoding: $encoding);
    }

    /**
     * Force a specific symbol version (1–40). Encoding throws
     * {@see \PHPdot\QrCode\Exception\EncodingException} if the payload is too
     * large for it.
     */
    public function forceVersion(int $version): self
    {
        return $this->with(forceVersion: $version);
    }

    /**
     * Toggle the ECI segment for non-Latin byte-mode encodings. Disable it for
     * legacy scanners that cannot read the UTF-8 ECI prefix.
     */
    public function eci(bool $eci): self
    {
        return $this->with(eci: $eci);
    }

    public function size(int $size): self
    {
        return $this->with(options: $this->options->withSize($size));
    }

    public function margin(int $margin): self
    {
        return $this->with(options: $this->options->withMargin($margin));
    }

    public function foreground(Color $color): self
    {
        return $this->with(options: $this->options->withForeground($color));
    }

    public function background(Color $color): self
    {
        return $this->with(options: $this->options->withBackground($color));
    }

    /**
     * The current rendering options.
     */
    public function options(): RenderOptions
    {
        return $this->options;
    }

    /**
     * Encode the configured data into a {@see QrCode} value object.
     */
    public function encode(): QrCode
    {
        return $this->encoder->encode(
            $this->data,
            $this->level,
            $this->encoding,
            $this->forceVersion,
            $this->eci,
        );
    }

    /**
     * The encoded module matrix, without rendering to an image.
     */
    public function toMatrix(): Matrix
    {
        return $this->encode()->matrix;
    }

    /**
     * Render to an SVG document string.
     */
    public function toSvg(): string
    {
        return $this->svg->render($this->toMatrix(), $this->options);
    }

    /**
     * Render to PNG binary.
     */
    public function toPng(): string
    {
        return $this->png->render($this->toMatrix(), $this->options);
    }

    /**
     * Render to a base64 `data:` URI in the given format (SVG by default).
     */
    public function toDataUri(ImageFormat $format = ImageFormat::Svg): string
    {
        $renderer = match ($format) {
            ImageFormat::Svg => $this->svg,
            ImageFormat::Png => $this->png,
        };

        return $this->dataUri->render($renderer, $this->toMatrix(), $this->options);
    }

    private function with(
        string|null $data = null,
        ErrorCorrection|null $level = null,
        string|null $encoding = null,
        int|null $forceVersion = null,
        bool|null $eci = null,
        RenderOptions|null $options = null,
    ): self {
        return new self(
            $this->encoder,
            $this->svg,
            $this->png,
            $this->dataUri,
            $data ?? $this->data,
            $level ?? $this->level,
            $encoding ?? $this->encoding,
            $forceVersion ?? $this->forceVersion,
            $eci ?? $this->eci,
            $options ?? $this->options,
        );
    }
}
