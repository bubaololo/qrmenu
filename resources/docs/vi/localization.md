# Đa ngôn ngữ

---

- [Ngôn ngữ nguồn & bộ chuyển](#source)
- [Dịch thủ công](#manual)
- [Dịch bằng AI](#ai)
- [Dự phòng khi chưa dịch](#fallback)
- [Đổi ngôn ngữ nguồn](#change-source)

<a name="source"></a>
## Ngôn ngữ nguồn & bộ chuyển

Mỗi thực đơn có **ngôn ngữ nguồn** (`source_locale`) — ngôn ngữ mà các văn bản gốc
được nhập vào. Phần đầu trình chỉnh sửa có **bộ chuyển ngôn ngữ nội dung**: nó
quyết định bạn đang chỉnh sửa/xem tên ở ngôn ngữ nào.

![Bộ chuyển ngôn ngữ](/img/docs/vi/locale.png)

> {warning} Danh mục và món mới chỉ có thể được tạo ở ngôn ngữ nguồn. Bản dịch của các mục có sẵn được chỉnh sửa ở các ngôn ngữ khác.

<a name="manual"></a>
## Dịch thủ công

Chuyển ngôn ngữ đang chọn sang ngôn ngữ đích và sửa tên/mô tả của món — một bản
dịch được lưu ở ngôn ngữ đó mà không đụng đến văn bản nguồn. Trên trang khách,
`?lang=<ngôn-ngữ>` sẽ hiển thị bản dịch.

<a name="ai"></a>
## Dịch bằng AI

Cạnh một ngôn ngữ chưa dịch có nút **✨ (dịch bằng AI)** — khởi chạy việc dịch nền
(qua hàng đợi). Đây là quá trình **bất đồng bộ**: bản dịch xuất hiện sau một lúc,
hãy làm mới trang.

> {info} Dịch bằng AI không cố định và phụ thuộc nhà cung cấp LLM. Để có kết quả đảm bảo, hãy sửa bản dịch thủ công.

<a name="fallback"></a>
## Dự phòng khi chưa dịch

Nếu một trường chưa được dịch sang ngôn ngữ yêu cầu, trang khách sẽ hiển thị văn
bản **ngôn ngữ nguồn**. Ví dụ: tên đã dịch sang `vi` nhưng mô tả thì chưa → ở
`?lang=vi`, tên là tiếng Việt còn mô tả là ngôn ngữ nguồn.

<a name="change-source"></a>
## Đổi ngôn ngữ nguồn

Trong bộ chuyển, một ngôn ngữ đã dịch có biểu tượng "ngôi sao" — **"Đặt làm ngôn
ngữ gốc"**. Điều kiện: ngôn ngữ đích phải được **dịch đầy đủ** (mọi trường nguồn).
Sau khi đổi `source_locale`, các bản dịch hiện có được giữ lại, còn các mục mới
được ghi bằng ngôn ngữ mới.

> {primary} Nếu bản dịch chưa đầy đủ, thao tác sẽ bị từ chối (hãy dịch tất cả các trường trước).
