# Asefa Chat AI

## ภาพรวมโปรเจกต์ (Project Overview)
Asefa Chat AI เป็นระบบแชทบอทอัจฉริยะแบบ Web-based ที่ถูกพัฒนาขึ้นเพื่อให้ผู้ใช้สามารถสนทนา ค้นหาข้อมูล และวิเคราะห์ไฟล์เอกสารต่างๆ ผ่าน AI Model ระดับโลกหลายค่ายในที่เดียว (Multimodal AI Platform) 

ด้วยอินเทอร์เฟซที่ทันสมัย ใช้งานง่าย (Material Design) และรองรับการปรับแต่งบุคลิกภาพ (Personality) ระบบจึงเหมาะสำหรับองค์กรที่ต้องการผู้ช่วยส่วนตัวอัจฉริยะที่สามารถรวบรวมข้อมูล ตอบคำถาม หรือสรุปเอกสารที่ซับซ้อนได้อย่างรวดเร็ว

## ฟีเจอร์หลัก (Key Features)
1. **รองรับ AI หลายค่าย (Multi-Model Support)**
   - **Anthropic Claude**: Claude 3.5 Sonnet, Claude 3 Haiku, Opus
   - **Google Gemini**: Gemini 2.5 Pro, Flash, Gemini 3
   - **OpenAI**: GPT-4o, GPT-4o Mini
   - **Perplexity (Web Search)**: Sonar Pro, Sonar Reasoning

2. **ระบบการวิเคราะห์และอัปโหลดไฟล์ (Multimodal Capabilities)**
   - รองรับการอัปโหลดไฟล์รูปภาพ (JPG, PNG, WEBP, etc.)
   - อ่านและประมวลผลไฟล์ Excel (`.xlsx`, `.xls`) และสรุปข้อมูลอัตโนมัติ
   - รองรับ Text-based format (TXT, CSV, JSON, MD)
   - มีระบบตรวจสอบ Signature ของไฟล์ (Magic Bytes) เพื่อความปลอดภัย (Security in Depth)

3. **การปรับแต่งบุคลิกภาพ AI (Custom AI Personality)**
   - ผู้ใช้งานสามารถตั้งค่าโทนเสียง (Tone), ความเป็นทางการ, หัวข้อที่สนใจพิเศษ
   - รองรับ Custom Instructions เพิ่มเติมเพื่อให้ AI ตอบคำถามได้ตรงกับบริบทขององค์กร

4. **ระบบการค้นหาบนเว็บไซต์ (Real-time Web Search)**
   - สามารถดึงข้อมูลปัจจุบันผ่านเครื่องมือค้นหาบนเว็บสำหรับ Model ที่รองรับ (OpenRouter Web Search Tool / Perplexity)

5. **ออกแบบด้วยดีไซน์ทันสมัย (Modern UI/UX)**
   - รองรับ Responsive Design ทำงานได้ทั้งบน Desktop และ Mobile
   - จัดเก็บประวัติการสนทนา (Chat History) อย่างเป็นระเบียบ

## เทคโนโลยีที่ใช้งาน (Tech Stack)
- **Frontend**: HTML5, CSS3 (Bootstrap 5.3), JavaScript (Axios, jQuery, SweetAlert2), Highlight.js, Marked.js
- **Backend**: PHP 8 (SSE - Server-Sent Events สำหรับ Stream Data)
- **Database**: SQL Server สำหรับจัดเก็บประวัติแชทและเซสชั่น (ChatSessions, ChatMessages)
- **API Provider**: OpenRouter API (สำหรับการเรียกใช้งาน AI Models)
- **Library เพิ่มเติม**: `phpoffice/phpspreadsheet` (สำหรับอ่านไฟล์ Excel)

## การติดตั้งและการรันระบบ (Setup & Installation)
1. Clone โปรเจกต์นี้ลงในเซิร์ฟเวอร์ (เช่น XAMPP, Nginx, หรือ IIS)
   ```bash
   git clone https://github.com/JakrayuSaithong/ChatAI-Asefa.git
   ```
2. ทำการตั้งค่าฐานข้อมูลในไฟล์ `api/db_connect.php` หรือ `.env`
3. รัน Composer เพื่อติดตั้ง Dependencies (โดยเฉพาะ PhpSpreadsheet)
   ```bash
   composer install
   ```
4. ติดตั้ง OpenRouter API Key ในไฟล์คอนฟิก (เช่น `api/config.php`)
5. เปิดเบราว์เซอร์แล้วเข้าสู่ `http://localhost/asefaChatAI`

## ความปลอดภัยของระบบ (Security Measures)
- ป้องกันการอัปโหลดไฟล์อันตราย (`.php`, `.exe`, `.sh`) ระดับ Signature/MIME Type
- ตรวจสอบขนาดไฟล์ (File Size Limit)
- เก็บประวัติแชทอย่างปลอดภัยใน SQL Server

## ข้อมูลนักพัฒนา / เจ้าของโปรเจกต์
- จัดทำและพัฒนาโดย ASEFA CSD Team
