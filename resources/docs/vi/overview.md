# Tổng quan trang quản trị

---

- [Đây là gì](#what)
- [Hai giao diện: quản trị & thực đơn khách](#surfaces)
- [Luồng quản lý thực đơn](#flow)
- [Bắt đầu nhanh](#quick-start)
- [Ngôn ngữ tài liệu](#language)

<a name="what"></a>
## Đây là gì

**QRMenu** là nền tảng thực đơn số cho nhà hàng. Khách quét mã QR và mở thực đơn
trên trình duyệt; nhân viên quản lý nội dung qua trang quản trị (ứng dụng React
"QRMenu Admin"). Tài liệu này mô tả **toàn bộ luồng quản lý thực đơn** trong
trang quản trị và cách mỗi thay đổi hiển thị trên trang dành cho khách.

<a name="surfaces"></a>
## Hai giao diện

> {primary} Mọi thay đổi trong trang quản trị đều được phản ánh trên **thực đơn khách công khai**. Tài liệu trình bày cả hai phía.

- **Quản trị** — trình chỉnh sửa thực đơn: danh mục, món, giá, tùy chọn, bản dịch.
- **Thực đơn khách** — trang công khai do máy chủ kết xuất tại `/{nhà-hàng}` (và
  `/{nhà-hàng}/t/{bàn}` để đặt món) mà khách nhìn thấy.

<a name="flow"></a>
## Luồng quản lý thực đơn

Việc quản lý thực đơn đi từ trên xuống:

1. **Thực đơn** được tạo cho một nhà hàng (mỗi nhà hàng một thực đơn).
2. **Danh mục** nhóm các món lại.
3. **Món** được thêm vào danh mục: tên, mô tả, giá, ảnh, cờ hiển thị.
4. **Tùy chọn** (kích cỡ, món thêm) được tạo trong thư viện dùng chung rồi gắn vào món.
5. **Ghi đè** thay đổi hành vi của một nhóm tùy chọn cho một món cụ thể.
6. **Đa ngôn ngữ** — dịch sang các ngôn ngữ khác.
7. **Thực đơn khách** — xem trước và nhận đơn.

Mỗi bước được mô tả trong mục tương ứng ở bên trái.

<a name="quick-start"></a>
## Bắt đầu nhanh

> {info} Để mở trình chỉnh sửa, hãy chọn nhà hàng và vào mục **Thực đơn**.

1. Mở trình chỉnh sửa thực đơn — bạn sẽ thấy tab **Món** và **Tùy chọn**.
2. Nhấn **Thêm danh mục**, nhập tên, lưu.
3. Mở danh mục và nhấn **Thêm món** — điền tên và giá.
4. Ở tab **Tùy chọn**, tạo một nhóm (ví dụ "Kích cỡ") từ mẫu có sẵn.
5. Trong trình chỉnh sửa món, gắn nhóm và đặt ghi đè nếu cần.
6. Mở bản xem trước (biểu tượng con mắt) — kiểm tra thực đơn hiển thị cho khách.

![Trình chỉnh sửa thực đơn](/img/docs/vi/editor.png)

<a name="language"></a>
## Ngôn ngữ tài liệu

> {success} Bộ chuyển phiên bản ở góc trên bên phải hoạt động như **bộ chuyển ngôn ngữ**: Русский (`ru`), English (`en`), Tiếng Việt (`vi`).
