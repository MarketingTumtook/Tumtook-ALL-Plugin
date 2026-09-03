# Tumtook All-in-One Modules

ปลั๊กอินนี้รวมปลั๊กอิน Tumtook ทั้งหมดที่แนบมาไว้ในปลั๊กอินเดียว โดยเก็บโค้ดเดิมไว้ใน `modules/` เพื่อให้ asset path, vendor, shortcode, Gutenberg block และ logic เดิมทำงานเหมือนเดิมมากที่สุด

## Modules Included

1. Tumtook Brand Showcase
2. Tumtook Download PDF Catalog
3. Tumtook Gallery
4. Tumtook Dynamic Comparison Table
5. Tumtook Page Article Recommendations
6. Tumtook Page Card Products ทั้งหมด
7. Tumtook Page Product Recommendations
8. Tumtook Job Working Cards
9. Tumtook Video How To Slider

## Shortcodes Included

- `[tumtook_brand_showcase]`
- `[tumtook_brand_showCase]`
- `[tumtook_catalog code="PDF"]`
- `[gallery_pdf code="PDF" text="ดาวน์โหลด PDF"]`
- `[tumtook_gallery]`
- `[tumtook_comparison]`
- `[tumtook_recommended_articles]`
- `[tumtook_product_cards]`
- `[tumtook_recommended_products]`
- `[tumtook_job_working_cards]`
- `[video_how_to_slider]`
- `[tumtook_video_how_to_slider]`
- `[tumtook_video_how_to_youtube]`
- `[tumtook_video_how_to_recommended_products]`

## Installation

1. Deactivate the old standalone Tumtook plugins first.
2. Upload `tumtook-all-in-one.zip` in WordPress Admin > Plugins > Add New > Upload Plugin.
3. Activate **Tumtook All-in-One Modules**.
4. Keep existing shortcodes and page meta as-is. The plugin preserves the original meta keys and shortcode names.

## Tumtook Job Working Cards

ใช้ shortcode `[tumtook_job_working_cards]` สำหรับดึง post type งานจาก JetEngine มาแสดงเป็น card slider โดย frontend class และ slider namespace จะใช้ `ttwc-*` ทั้งหมด

### วิธีใช้ Shortcode

- แสดง Job Working posts ทั้งหมด: `[tumtook_job_working_cards]`
- ระบุ post type เอง: `[tumtook_job_working_cards post_type="job-working"]`
- เลือก category ด้วย slug หรือชื่อ category: `[tumtook_job_working_cards taxonomy="job-category" category="design"]`
- เลือกหลาย category: `[tumtook_job_working_cards taxonomy="job-category" category="design,marketing"]`
- เลือก category ด้วย term id: `[tumtook_job_working_cards taxonomy="job-category" category_id="12"]`
- จำกัดจำนวนเองเฉพาะกรณีที่ต้องการ: `[tumtook_job_working_cards limit="8"]`

### คำอธิบาย Attribute

- `post_type`: slug ของ post type งาน ถ้าไม่ใส่ ระบบจะลองหา `job-working`, `job_working`, `jobworking`, `jobs`, `job` ให้อัตโนมัติ
- `taxonomy`: slug ของ taxonomy ที่ใช้กรอง category เช่น `job-category`
- `category`: slug, ชื่อ category, หรือ id ของ term ใช้คั่นหลายค่าด้วย comma หรือขึ้นบรรทัดใหม่ได้
- `category_id`: id ของ term โดยตรง ใช้คั่นหลายค่าด้วย comma ได้
- `limit`: จำนวน card ที่ต้องการแสดง ถ้าไม่ใส่หรือปล่อยว่าง จะแสดงรายการที่ match ทั้งหมด
- `title`: หัวข้อ section ค่าเริ่มต้นคือ `งานที่น่าสนใจ`
- `view_all_label`: ข้อความลิงก์ดูทั้งหมด ค่าเริ่มต้นคือ `ดูงานทั้งหมด`
- `view_all_url`: URL ของลิงก์ดูทั้งหมด
- `button_label`: ข้อความปุ่มในการ์ด ค่าเริ่มต้นคือ `ดูรายละเอียด`
- `info_meta`: meta key สำหรับข้อความรายละเอียดสั้นในการ์ด เช่น เงินเดือน/สถานที่
- `badge_meta`: meta key สำหรับ badge ถ้าไม่ใส่จะใช้ term แรกจาก taxonomy
- `image_meta`: meta key รูปภาพ ถ้าไม่ใส่จะใช้ featured image ก่อน
- `orderby`: วิธีเรียงข้อมูล รองรับ `rand`, `date`, `title`, `menu_order`
- `include`: post id ที่ต้องการแสดงเท่านั้น คั่นด้วย comma ได้
- `exclude`: post id ที่ไม่ต้องการแสดง คั่นด้วย comma ได้

ถ้าไม่ใส่ `category` และ `category_id` ระบบจะแสดงทุก category และถ้าไม่ใส่ `limit` จะไม่มีการจำกัดจำนวน card.

## Important

Do not activate this combined plugin together with the old standalone versions. If both are active, PHP class/function conflicts may occur. The loader includes basic guard checks and admin notices, but the cleanest setup is to use only this combined plugin.

## Version 1.0.1

- Fixed Tumtook catalog image rendering by adding a same-origin WordPress image proxy fallback.
- Catalog images now use server-side cached image URLs first and fall back to the original remote image URL if needed.
- Added support for multiple possible API image URL fields such as `fileUrl`, `imageUrl`, `url`, and `path`.
