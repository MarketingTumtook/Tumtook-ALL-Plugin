# Tumtook Gallery Code

โมดูลแยกสำหรับวาง Gallery ด้วย code โดยไม่ต้องตั้งค่าราย Page หรือใช้ `[tumtook_gallery]` เดิม

## ทดสอบ API ใน Settings

ใน **Settings → Tumtook Gallery Code** ใส่ URL แล้วกด **ทดสอบ URL และดูข้อมูล Gallery** ได้ทันทีโดยไม่ต้องบันทึก:

- เว้น code ว่างเพื่อดูจำนวนรายการ, code ที่พบ, จำนวน URL รูป และรูปตัวอย่าง
- กรอก code เช่น `KH` เพื่อดูว่ามีรายการตรงกันและรูปพร้อมแสดงหรือไม่
- ผลทดสอบใช้ข้อมูล API ล่าสุดและ URL ที่กรอกในขณะนั้น โดยไม่แก้ค่าที่บันทึกไว้
- เมื่อทดสอบถูกต้องแล้วจึงกด **บันทึกการเปลี่ยนแปลง** เพื่อใช้ URL กับ shortcode จริง
- เฉพาะผู้ดูแลที่มี `manage_options` จึงทดสอบ URL ได้ และคำขอใช้ HTTP แบบ safe ฝั่งเซิร์ฟเวอร์

ตั้งค่า API URL ครั้งเดียวใน **Settings → Tumtook Gallery Code** แล้วใช้:

```text
[tumtook_gallery_code code="VN"]
[tumtook_gallery_code code="MT"]
```

สำหรับ field ของ JetEngine ใน Post Type ใช้:

```text
[tumtook_gallery_code field="tt_gallery_code"]
```

หรือใช้ `[tumtook_gallery_code]` โดยไม่ใส่ attribute เลย จะอ่าน field `tt_gallery_code` เป็นค่าเริ่มต้น เช่น field มีค่า `VN` ก็จะแสดง Gallery ของ VN

ใน JetEngine Listing จะอ่านจากโพสต์ของ Listing item นั้น ไม่ใช่หน้าแม่หรือ Elementor template และใช้ meta API ของ JetEngine เพื่อรองรับการเก็บ meta แบบกำหนดเอง เมื่อ JetEngine ไม่ทำงานจะอ่าน post meta ของโพสต์ปัจจุบันแทน

หากใส่ `code="MT"` โดยตรง จะใช้ค่านั้นก่อนค่า field เสมอ หาก field ว่างหรือไม่ใช่ code เดี่ยวที่ถูกต้อง จะไม่เรียก API และไม่แสดง Gallery แทนด้วย code ของหน้าแม่

## ตรวจข้อมูลในหลังบ้าน

หลังบันทึก field `tt_gallery_code` แล้ว จะมีกล่อง **Tumtook Gallery Code — ตรวจสอบข้อมูล** ในหน้าแก้ไขโพสต์ที่มี field นี้:

1. บันทึกหรืออัปเดตโพสต์ก่อน เพื่อใช้ค่าที่บันทึกจริง
2. กด **ตรวจข้อมูลและดูตัวอย่าง**
3. ตรวจโพสต์ ID, code ที่อ่านได้, HTTP ของข้อมูล API, แหล่งข้อมูลแคช, จำนวนรายการที่ code ตรงกัน, จำนวน URL รูป และจำนวนรูปไม่ซ้ำที่พร้อมแสดง
4. ดูรูปตัวอย่างสูงสุด 8 รูป เพื่อเทียบว่าเป็นผลงานที่ต้องการจริง

เลือก **อ่าน API ล่าสุดโดยข้ามแคช** เมื่อต้องการตรวจข้อมูลใหม่โดยตรง กล่องนี้ไม่เรียก API ตอนเปิดหน้าแก้ไข และไม่เพิ่มข้อความตรวจสอบบนหน้าเว็บ เฉพาะผู้มีสิทธิ์แก้ไขโพสต์นั้นพร้อม nonce ที่ถูกต้องเท่านั้นจึงเรียกตรวจได้ โดยไม่ส่ง API URL หรือ API key ไปที่เบราว์เซอร์

- ใช้หนึ่ง code ต่อ shortcode และวางหลาย shortcode ในหน้าเดียวกันได้
- แต่ละ instance เลือกภาพตาม code ของตัวเอง ไม่อ่านหรือเขียนค่าของ Gallery เดิม
- API ใช้รูปแบบ `items[].code` และ `items[].images[].fileUrl` พร้อม `altText`, `width`, `height` ถ้ามี
- ค่าเริ่มต้น: แคช 30 นาที สูงสุด 50 รูป ไม่สุ่มลำดับ และใช้สัดส่วนจริงของรูป
- คอลัมน์ปรับตามพื้นที่และหน้าจอ กว้างสุด 6 คอลัมน์ แท็บเล็ตไม่เกิน 3 และมือถือ 2
- คลิกรูปเพื่อเปิด lightbox พร้อมปุ่มก่อนหน้า/ถัดไปและเลขรูป รองรับคีย์บอร์ดลูกศรกับ Escape โดยเปลี่ยนรูปเฉพาะชุด shortcode ที่คลิก
- โหลดข้อมูลเมื่อใกล้หน้าจอหนึ่งครั้งต่อ instance และใช้ native lazy loading สำหรับรูป
- instance ที่ใช้ code เดียวกันในหน้าเดียวกันใช้คำขอข้อมูลร่วมกัน ส่วน cache ของ API ใช้กลไกป้องกันคำขอซ้ำจากส่วนกลาง
- API URL เก็บฝั่งเซิร์ฟเวอร์ โดย frontend ส่งเฉพาะ code พร้อมลายเซ็นของ shortcode
- ไม่มีปุ่ม retry หรือข้อความ API error บนหน้า production
- แยก class, shortcode, REST route, option, CSS และ JavaScript จาก `tumtook-gallery` เดิมทั้งหมด ไม่ผูกกับ meta box ของ Page

ทดสอบโดยไม่เรียก API จริง:

```sh
php modules/tumtook-gallery-code/tests/smoke.php
node modules/tumtook-gallery-code/tests/frontend.js
node modules/tumtook-gallery-code/tests/admin.js
```
