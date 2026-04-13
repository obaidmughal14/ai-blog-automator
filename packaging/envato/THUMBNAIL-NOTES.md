# Thumbnail & preview images

## Included icon

The plugin ships **`assets/images/icon-256.png`** (256×256 PNG). Use it as a base for:

- Resizing to Envato’s **80×80** item icon (if required).
- Favicon-style branding in documentation.

Resize in Photoshop, Figma, or ImageMagick:

```bash
magick assets/images/icon-256.png -resize 80x80 thumbnail-80.png
```

## Main preview (590×300 typical)

Envato often wants a **wide banner** with product name + tagline, not only the square icon. Design a 590×300 (or current spec) JPEG in Figma/Canva using:

- Product name: **AI Blog Automator**
- Short tagline: e.g. “AI drafts, SEO, images & queue for WordPress”
- Optional: small screenshot inset.

Keep file size reasonable (under 500 KB if possible) for fast loading on the marketplace.
