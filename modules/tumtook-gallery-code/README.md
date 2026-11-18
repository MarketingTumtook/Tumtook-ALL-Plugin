# Tumtook Gallery Code

โมดูลแยกสำหรับวาง Gallery ด้วย code โดยไม่ต้องตั้งค่าราย Page หรือใช้ `[tumtook_gallery]` เดิม

ตั้งค่า API URL ครั้งเดียวใน **Settings → Tumtook Gallery Code** แล้วใช้:

```text
[tumtook_gallery_code code="VN"]
[tumtook_gallery_code code="MT"]
```

- ใช้หนึ่ง code ต่อ shortcode และวางหลาย shortcode ในหน้าเดียวกันได้
- แต่ละ instance เลือกภาพตาม code ของตัวเอง ไม่อ่านหรือเขียนค่าของ Gallery เดิม
- API ใช้รูปแบบ `items[].code` และ `items[].images[].fileUrl` พร้อม `altText`, `width`, `height` ถ้ามี
- ค่าเริ่มต้น: แคช 30 นาที สูงสุด 50 รูป ไม่สุ่มลำดับ และใช้สัดส่วนจริงของรูป
- คอลัมน์ปรับตามพื้นที่และหน้าจอ กว้างสุด 6 คอลัมน์ แท็บเล็ตไม่เกิน 3 และมือถือ 2
- คลิกรูปเพื่อดูไฟล์เต็มในแท็บใหม่
- โหลดข้อมูลเมื่อใกล้หน้าจอหนึ่งครั้งต่อ instance และใช้ native lazy loading สำหรับรูป
- instance ที่ใช้ code เดียวกันในหน้าเดียวกันใช้คำขอข้อมูลร่วมกัน ส่วน cache ของ API ใช้กลไกป้องกันคำขอซ้ำจากส่วนกลาง
- API URL เก็บฝั่งเซิร์ฟเวอร์ โดย frontend ส่งเฉพาะ code พร้อมลายเซ็นของ shortcode
- ไม่มีปุ่ม retry หรือข้อความ API error บนหน้า production
- แยก class, shortcode, REST route, option, CSS และ JavaScript จาก `tumtook-gallery` เดิมทั้งหมด ไม่ผูกกับ meta box ของ Page

ทดสอบโดยไม่เรียก API จริง:

```sh
php modules/tumtook-gallery-code/tests/smoke.php
node modules/tumtook-gallery-code/tests/frontend.js
```
