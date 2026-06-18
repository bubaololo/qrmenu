# Danh mục & món

---

- [Mở trình chỉnh sửa](#open)
- [Danh mục](#sections)
- [Món](#items)
- [Giá](#price)
- [Cờ hiển thị](#flags)
- [Ảnh, nhân bản, xóa](#misc)

<a name="open"></a>
## Mở trình chỉnh sửa

Chọn nhà hàng và mở mục **Thực đơn**. Trình chỉnh sửa mở ở tab **Món**; bên cạnh
là tab **Tùy chọn**. Góc trên bên phải: bộ chuyển ngôn ngữ nội dung và nút xem
trước (biểu tượng con mắt).

![Trình chỉnh sửa thực đơn](/img/docs/vi/editor.png)

<a name="sections"></a>
## Danh mục

Danh mục dùng để nhóm các món.

- **Tạo:** nút **Thêm danh mục** → nhập tên → **Lưu**.
- **Đổi tên:** sửa tên ở hàng danh mục rồi lưu.
- **Thứ tự:** kéo danh mục bằng tay cầm ("⋮⋮") — thứ tự áp dụng ngay trên thực
  đơn khách.
- **Ẩn/hiện:** nút con mắt (cờ kích hoạt). Danh mục bị ẩn cùng toàn bộ món của nó
  **biến mất khỏi trang khách**; bật lại để hiển thị trở lại.
- **Xóa:** biểu tượng thùng rác → xác nhận. Xóa danh mục sẽ xóa luôn các món của nó.

> {primary} Danh mục bị tắt sẽ không được gửi ra trang khách (lọc ở máy chủ), chứ không chỉ ẩn về mặt hình ảnh.

<a name="items"></a>
## Món

Mở một danh mục và nhấn **Thêm món** — trình chỉnh sửa món sẽ mở ra.

![Trình chỉnh sửa món](/img/docs/vi/item.png)

Các trường:

- **Tên** (bắt buộc) và **Mô tả** — theo ngôn ngữ đang chọn.
- **Giá** — xem bên dưới.
- **Ảnh** — tải lên với khung cắt 1:1.
- **Tùy chọn** — gắn nhóm (xem [Tùy chọn](/{{route}}/{{version}}/modifiers)).
- **Cờ** — hiển thị, đặt món được, gợi ý.

> {warning} Không thể lưu khi tên ở ngôn ngữ đang chọn còn trống.

<a name="price"></a>
## Giá

Trình chỉnh sửa món có một trường giá dạng số — tạo giá **cố định** (`fixed`).
Trên thực đơn khách, giá hiển thị là số nguyên có dấu phân tách hàng nghìn kèm ký
hiệu tiền tệ (ví dụ `60 000 ₫`).

> {info} Giá khoảng / "từ" / biến đổi (range/from/variable) tồn tại trong dữ liệu và nhận dạng thực đơn, nhưng **không được tạo thủ công** trong trình chỉnh sửa món.

<a name="flags"></a>
## Cờ hiển thị

- **Hiển thị trong thực đơn** (`is_visible`): tắt — món **biến mất** khỏi trang khách.
- **Đặt món được** (`is_orderable`): tắt — món vẫn hiển thị, nhưng **nút "Thêm"
  trong bảng món bị ẩn** (không thể đặt).
- **Gợi ý** (`starred`): đánh dấu món bằng ngôi sao trên thực đơn khách.

> {info} Tắt "Hiển thị trong thực đơn" sẽ tự động tắt "Đặt món được" và "Gợi ý".

<a name="misc"></a>
## Ảnh, nhân bản, xóa

- **Ảnh:** việc tải lên là bất đồng bộ — sau khi lưu món, ảnh sẽ xuất hiện trên
  trang khách (kèm làm mới bộ nhớ đệm).
- **Nhân bản:** biểu tượng sao chép ở hàng món → xác nhận. Bản sao được tạo với
  cùng thuộc tính, bản dịch và **các nhóm tùy chọn đã gắn**.
- **Xóa:** từ trình chỉnh sửa món (biểu tượng thùng rác trên đầu) → xác nhận.
- **Sắp xếp món:** kéo thả trong danh mục; thứ tự áp dụng cho trang khách.
