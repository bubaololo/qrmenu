# Thực đơn cho khách

---

- [Địa chỉ trang](#urls)
- [Cách hiển thị tùy chọn](#render)
- [Xem trước giá & nút đặt](#price)
- [Đặt món & ảnh chụp](#order)

<a name="urls"></a>
## Địa chỉ trang

- **Xem:** `/{nhà-hàng}` (và `/{nhà-hàng}/{ngôn-ngữ}`), trong đó đoạn đầu là id số
  hoặc uniqid của nhà hàng.
- **Đặt món tại bàn:** `/{nhà-hàng}/t/{bàn}/{ngôn-ngữ?}` — chỉ trên URL này mới
  đặt món được (cần định danh bàn).

![Thực đơn cho khách](/img/docs/vi/guest.png)

<a name="render"></a>
## Cách hiển thị tùy chọn

Khi chạm vào thẻ món, một bảng tùy chọn mở ra:

- **Nhóm `replace` (Kích cỡ)** — chip chọn-một; lựa chọn "mặc định" được chọn sẵn.
- **Nhóm `add` (Món thêm)** — chọn nhiều; mỗi lựa chọn hiển thị `+chênh lệch`.

![Bảng món](/img/docs/vi/guest-sheet.png)

<a name="price"></a>
## Xem trước giá & nút đặt

Tổng tiền được tính trực tiếp: giá gốc (hoặc giá tuyệt đối của kích cỡ đã chọn với
`replace`) **cộng** tổng chênh lệch của các món thêm đã chọn (có tính giá theo
kích cỡ).

Nút "Thêm" bị **chặn** nếu quy tắc chọn của nhóm `add`
(`selection_min`/`selection_max`) chưa thỏa. Nhóm `replace` không chặn nút (luôn
có một lựa chọn được chọn sẵn). Với món không đặt được (`is_orderable=false`), nút
bị ẩn.

<a name="order"></a>
## Đặt món & ảnh chụp

Trên URL có bàn, thêm món vào giỏ và đặt đơn.

> {primary} Giá do **máy chủ** tính từ thực đơn hiện tại — phía khách không tự đặt giá (chống giả mạo). Bộ lựa chọn không hợp lệ (vi phạm tối thiểu/tối đa/bắt buộc, hoặc lựa chọn của món khác) sẽ bị từ chối (422).

> {success} **Lịch sử đơn hàng là bất biến.** Tại thời điểm đặt, ảnh chụp tên và giá được lưu lại. Nếu sau đó món bị sửa hoặc xóa trong trang quản trị — thực đơn cập nhật, nhưng dòng đơn hàng vẫn giữ tên và giá cũ.
