# Tumtook All-in-One Modules

ปลั๊กอินนี้รวมปลั๊กอิน Tumtook ทั้งหมดที่แนบมาไว้ในปลั๊กอินเดียว โดยเก็บโค้ดเดิมไว้ใน `modules/` เพื่อให้ asset path, vendor, shortcode และ logic เดิมทำงานเหมือนเดิมมากที่สุด

## Modules Included

1. Tumtook Brand Showcase
2. Tumtook Download PDF Catalog
3. Tumtook Gallery
4. Tumtook Page Article Recommendations
5. Tumtook Page Product Cards
6. Tumtook Page Product Recommendations
7. Tumtook Video How To Slider

## Shortcodes Included

- `[tumtook_brand_showcase]`
- `[tumtook_brand_showCase]`
- `[tumtook_catalog code="PDF"]`
- `[gallery_pdf code="PDF" text="ดาวน์โหลด PDF"]`
- `[tumtook_gallery]`
- `[tumtook_recommended_articles]`
- `[tumtook_product_cards]`
- `[tumtook_recommended_products]`
- `[video_how_to_slider]`
- `[tumtook_video_how_to_slider]`
- `[tumtook_video_how_to_youtube]`
- `[tumtook_video_how_to_recommended_products]`

## Installation

1. Deactivate the old standalone Tumtook plugins first.
2. Upload `tumtook-all-in-one.zip` in WordPress Admin > Plugins > Add New > Upload Plugin.
3. Activate **Tumtook All-in-One Modules**.
4. Keep existing shortcodes and page meta as-is. The plugin preserves the original meta keys and shortcode names.

## Important

Do not activate this combined plugin together with the old standalone versions. If both are active, PHP class/function conflicts may occur. The loader includes basic guard checks and admin notices, but the cleanest setup is to use only this combined plugin.

## Version 1.0.1

- Fixed Tumtook catalog image rendering by adding a same-origin WordPress image proxy fallback.
- Catalog images now use server-side cached image URLs first and fall back to the original remote image URL if needed.
- Added support for multiple possible API image URL fields such as `fileUrl`, `imageUrl`, `url`, and `path`.
