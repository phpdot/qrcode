# phpdot/qrcode

Coroutine-safe QR code generation for the PHPdot ecosystem. Encode any string to
a QR symbol and render it as **SVG** (pure string, no extension), **PNG** (GD), a
raw **module matrix**, or an inline **`data:` URI** — through one immutable,
injectable factory.

Encoding is delegated to the battle-tested [`bacon/bacon-qr-code`][bacon] (numeric,
alphanumeric, byte and Kanji modes, ECI, all four error-correction levels,
versions 1–40, automatic masking). Everything bacon touches is hidden behind a
single `Encoder` class; the rest of the package — the value objects, the
renderers, the fluent builder — is plain PHPdot code with zero other runtime
dependencies.

## Install

```bash
composer require phpdot/qrcode
```

| Requirement | Version |
|---|---|
| PHP | >= 8.3 |
| ext-gd | required (PNG rendering) |
| bacon/bacon-qr-code | ^3.0 |

## Quick Start

```php
use PHPdot\QrCode\QrCodeFactory;

$qr = new QrCodeFactory(); // or inject it — see "DI Wiring"

// One-call helpers
$svg = $qr->svg('https://phpdot.com');
$png = $qr->png('https://phpdot.com');
$uri = $qr->dataUri('https://phpdot.com'); // data:image/svg+xml;base64,...

// Fluent build for anything more
use PHPdot\QrCode\Color;
use PHPdot\QrCode\Enum\ErrorCorrection;

$svg = $qr->create('https://phpdot.com')
    ->errorCorrection(ErrorCorrection::High)
    ->size(400)
    ->margin(2)
    ->foreground(Color::fromHex('#0b5'))
    ->toSvg();
```

## Why This Package

- **Coroutine-native.** The default renderer is a pure string — no GD, no temp
  files, nothing that blocks the Swoole scheduler. PNG is available when you need
  a raster, and the docs are explicit about it being CPU-bound.
- **Immutable everywhere.** `QrCode`, `Matrix`, `Color` and `RenderOptions` are
  `readonly` value objects; the builder clones on every setter, so a configured
  builder is a safe reusable template.
- **One dependency, well-fenced.** bacon is reached only through `Encoder`. Swap
  the backend and nothing else changes; mock the `Encoder` and the renderers test
  in isolation.
- **Injectable, not static.** No static factories, no global state — get a
  `QrCodeFactory` from the container and build from there.
- **Strict.** `declare(strict_types=1)` throughout, PHPStan level 10 with strict
  rules, zero ignored errors.

## Architecture

```
src/
├── QrCodeFactory.php          #[Singleton] — inject this; hands out builders
├── QrCodeBuilder.php          immutable fluent builder + terminal renders
├── QrCode.php                 encoded symbol value object (matrix + metadata)
├── Matrix.php                 immutable square grid of dark/light modules
├── RenderOptions.php          size · margin · foreground · background
├── Color.php                  immutable RGBA value object
├── Contract/
│   └── RendererInterface.php  render(Matrix, RenderOptions): string
├── Encoder/
│   └── Encoder.php            #[Singleton] — the only bacon boundary
├── Enum/
│   ├── ErrorCorrection.php    Low · Medium · Quartile · High
│   └── ImageFormat.php        Svg · Png (MIME + extension)
├── Renderer/
│   ├── SvgRenderer.php        #[Singleton] pure-string SVG (run-length merged)
│   ├── PngRenderer.php        #[Singleton] GD raster, alpha-aware
│   └── DataUriRenderer.php    #[Singleton] wraps a renderer → base64 data URI
└── Exception/
    ├── QrCodeException.php     base — catch this for anything from the package
    ├── EncodingException.php   payload too large / bad version / bad encoding
    └── RenderException.php     GD allocation or PNG encoding failure
```

Flow: `QrCodeFactory` → `QrCodeBuilder` → `Encoder::encode()` produces a
`QrCode` (carrying a `Matrix`) → a `RendererInterface` turns the `Matrix` +
`RenderOptions` into bytes.

## API Reference

### `QrCodeFactory` (inject this)

| Method | Returns | Notes |
|---|---|---|
| `create(string $data = '')` | `QrCodeBuilder` | Start a fluent build. |
| `make(string $data)` | `QrCode` | Encode with defaults, no rendering. |
| `svg(string $data)` | `string` | One-call SVG. |
| `png(string $data)` | `string` | One-call PNG binary. |
| `dataUri(string $data, ImageFormat $format = Svg)` | `string` | One-call data URI. |

### `QrCodeBuilder` (immutable — every setter returns a new builder)

| Setter | Purpose |
|---|---|
| `data(string)` | Content to encode. |
| `errorCorrection(ErrorCorrection)` | Damage-recovery level (default `Medium`). |
| `encoding(string)` | Byte-mode charset (default `UTF-8`). |
| `forceVersion(int)` | Pin symbol version 1–40 (throws if too small). |
| `eci(bool)` | Toggle the ECI prefix for non-Latin byte mode (default on). |
| `size(int)` · `margin(int)` | Pixel edge target · quiet-zone width in modules. |
| `foreground(Color)` · `background(Color)` | Module and backdrop colors. |

| Terminal | Returns |
|---|---|
| `encode()` | `QrCode` |
| `toMatrix()` | `Matrix` |
| `toSvg()` | `string` |
| `toPng()` | `string` |
| `toDataUri(ImageFormat = Svg)` | `string` |
| `options()` | `RenderOptions` (current) |

### `Color`

```php
new Color(0, 187, 85);              // r, g, b, alpha=255
Color::fromHex('#0b5');             // #rgb / #rrggbb / #rrggbbaa, '#' optional
Color::black(); Color::white(); Color::transparent();
$c->toHex(); $c->toCss(); $c->isTransparent();
```

Channels are validated to `0–255`; an out-of-range value throws
`InvalidArgumentException`.

### `RenderOptions`

`size` (default `300`) is the target square edge in pixels including the quiet
zone. `margin` (default `4`) is the quiet zone width in modules. SVG honours
`size` exactly via a `viewBox`; PNG snaps each module to whole pixels, so the PNG
may be slightly smaller than `size`.

## Working Examples

### Inside a controller

```php
use PHPdot\QrCode\QrCodeFactory;

final class TicketController
{
    public function __construct(private QrCodeFactory $qr) {}

    public function qr(string $ticketId): string
    {
        // SVG straight into the response body — no GD, no blocking.
        return $this->qr->svg("https://phpdot.com/t/{$ticketId}");
    }
}
```

### Styled, high-recovery PNG

```php
use PHPdot\QrCode\Color;
use PHPdot\QrCode\Enum\ErrorCorrection;

$png = $qr->create('WIFI:T:WPA;S:phpdot;P:secret;;')
    ->errorCorrection(ErrorCorrection::High)
    ->size(512)
    ->foreground(Color::fromHex('#101828'))
    ->background(Color::fromHex('#f8fafc'))
    ->toPng();

file_put_contents('wifi.png', $png);
```

### Transparent overlay

```php
$png = $qr->create('https://phpdot.com')
    ->background(Color::transparent())
    ->toPng(); // real alpha channel
```

### Inline in a template

```php
$src = $qr->dataUri('https://phpdot.com'); // <img src="...">
// or PNG: $qr->dataUri('https://phpdot.com', ImageFormat::Png);
```

### Pin a version

```php
use PHPdot\QrCode\Exception\EncodingException;

try {
    $svg = $qr->create($payload)->forceVersion(10)->toSvg();
} catch (EncodingException $e) {
    // payload too large for version 10
}
```

## Output Formats

| Format | Renderer | Extension needed | Coroutine | Best for |
|---|---|---|---|---|
| SVG | `SvgRenderer` | none | non-blocking | web responses, scaling, default |
| PNG | `PngRenderer` | ext-gd | CPU-bound, blocks | rasters, downloads, email |
| Data URI | `DataUriRenderer` | per wrapped renderer | follows wrapped | inline `<img>` / CSS |
| Matrix | `toMatrix()` | none | non-blocking | custom rendering |

## Encoding & ECI

Byte-mode content defaults to **UTF-8 with an ECI segment** (`eci(true)`), which
is the specification-correct way to signal a non-ISO-8859-1 charset. Most modern
(phone) scanners read it fine, but some legacy hardware scanners cannot read the
UTF-8 ECI prefix. If you target those, disable it:

```php
$svg = $qr->create('https://phpdot.com')->eci(false)->toSvg();
```

ASCII content (URLs, numbers) is unaffected — bacon only emits the ECI segment
for non-Latin byte-mode payloads.

## Error Handling

| Exception | When |
|---|---|
| `EncodingException` | Content too large for the level/forced version, or an invalid version/encoding. |
| `RenderException` | GD cannot allocate the image/color or encode the PNG. |
| `QrCodeException` | Base type — catch this to trap any package failure. |
| `InvalidArgumentException` | Programmer error: a bad hex string or out-of-range color/option. |

## Development

```bash
composer test      # PHPUnit (Unit + Integration)
composer analyse   # PHPStan level 10, strict rules
composer cs-check  # php-cs-fixer dry run
composer check     # all three
```

The PNG renderer is covered by a pixel round-trip test: a symbol is rendered,
the pixels are read back, the matrix is reconstructed, and it is asserted equal
to the source — so a renderer that painted the wrong cells would fail even though
the encoder itself is trusted.

## License

MIT — see [LICENSE](LICENSE).

[bacon]: https://github.com/Bacon/BaconQrCode
