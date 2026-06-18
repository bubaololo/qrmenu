# Tùy chọn (modifier)

---

- [Mô hình: thư viện + gắn vào món](#model)
- [Mẫu có sẵn (preset)](#presets)
- [Tạo nhóm](#create)
- [Lựa chọn và "mặc định"](#options)
- [Chỉnh sửa dùng chung](#shared)
- [Giá theo kích cỡ (Nâng cao)](#size-pricing)
- [Ghi đè theo món](#overrides)

<a name="model"></a>
## Mô hình: thư viện + gắn vào món

Tùy chọn (kích cỡ, món thêm) là một **thư viện nhóm dùng chung** được **gắn** vào
các món:

- Một **nhóm** có chế độ giá và quy tắc chọn: `pricing_mode` (`replace` — giá lựa
  chọn thay thế giá món; `add` — phụ thu), số lượng chọn tối thiểu/tối đa và cờ
  "bắt buộc".
- Mỗi **lựa chọn** trong nhóm có giá: với `replace` đó là giá **tuyệt đối** của
  lựa chọn, với `add` đó là **chênh lệch** (phụ thu).
- Nhóm được tạo một lần và **gắn vào nhiều món**; sửa nhóm sẽ thay đổi nó trên tất
  cả các món đã gắn cùng lúc.

![Tab Tùy chọn](/img/docs/vi/modifiers.png)

> {primary} Việc chỉnh sửa chính nhóm (tên lựa chọn, giá) CHỈ nằm ở tab Tùy chọn. Trong trình chỉnh sửa món, bạn chỉ gắn nhóm và đặt ghi đè.

<a name="presets"></a>
## Mẫu có sẵn (preset)

Mẫu thiết lập `pricing_mode` + tối thiểu/tối đa + "bắt buộc" một cách nhất quán:

Mẫu | Chế độ | Chọn | Hành vi
--- | ------ | ---- | -------
**Chọn một · giá thay thế** | `replace` | một | tối thiểu 1, tối đa 1, bắt buộc — "Kích cỡ"
**Chọn một · phụ thu (+)** | `add` | một | tối thiểu 1, tối đa 1, bắt buộc
**Số lượng tùy ý · phụ thu (+)** | `add` | nhiều | tối thiểu 0, không giới hạn — "Món thêm"
**Tối đa N · phụ thu (+)** | `add` | nhiều | tối thiểu 0, tối đa N

<a name="create"></a>
## Tạo nhóm

Ở tab **Tùy chọn** → **Thêm nhóm**:

1. Nhập tên nhóm (ví dụ "Kích cỡ").
2. Chọn một mẫu.
3. Điền các lựa chọn: tên và giá. Với `add`, dấu "+" hiển thị cạnh giá.
4. **Lưu**.

![Nhóm tùy chọn](/img/docs/vi/modifier-group.png)

Bộ đếm **"trong N món"** trong danh sách cho biết nhóm được dùng ở bao nhiêu món.
Xóa một nhóm đang được dùng sẽ hỏi xác nhận và gỡ nó khỏi các món đó.

<a name="options"></a>
## Lựa chọn và "mặc định"

- **Thêm lựa chọn** — thêm một lựa chọn; không thể xóa lựa chọn cuối cùng.
- **"Mặc định"** (chỉ với `replace` + một): đánh dấu lựa chọn được chọn sẵn. Trên
  trang khách, lựa chọn này được chọn khi mở — nếu không, giá và việc kiểm tra sẽ
  bị lệch.

<a name="shared"></a>
## Chỉnh sửa dùng chung

Nhóm là dùng chung. Thay đổi giá một lựa chọn trong thư viện sẽ thay đổi nó **trên
mọi món** mà nhóm được gắn. Có thể kiểm chứng trên trang khách: sửa giá "S" được
phản ánh trong chip kích cỡ của từng món như vậy.

<a name="size-pricing"></a>
## Giá theo kích cỡ (Nâng cao)

Đôi khi một món thêm có giá khác nhau tùy kích cỡ. Điều này được cấu hình trong
khối **"Nâng cao"** của một nhóm `add`:

1. Mở nhóm `add` (ví dụ "Món thêm") và mở rộng **"Nâng cao"**.
2. Bật **"Giá phụ thuộc nhóm khác"** và chọn **nhóm nguồn** (loại chọn-một,
   thường là "Kích cỡ").
3. Điền **lưới giá**: giá của từng lựa chọn cho từng kích cỡ.

![Giá theo kích cỡ](/img/docs/vi/size-pricing.png)

Trên trang khách, khi chọn kích cỡ, chênh lệch của món thêm và tổng tiền thay đổi
theo lưới.

> {info} Ô trống trong lưới = giá gốc của lựa chọn; nếu chưa chọn kích cỡ nguồn, cũng quay về giá gốc.

<a name="overrides"></a>
## Ghi đè theo món

Nhóm là dùng chung, nhưng quy tắc của nó có thể được **ghi đè cho một món cụ thể**
— trong trình chỉnh sửa món, mục **Tùy chọn**:

- **Gắn/gỡ** nhóm (công tắc). Việc gắn không thay đổi nhóm dùng chung — các món
  khác không bị ảnh hưởng.
- **Bắt buộc**, **Tối thiểu**, **Tối đa** — ghi đè chỉ cho món này.

![Gắn nhóm và ghi đè](/img/docs/vi/item-modifiers.png)

> {success} **Quan trọng (bắt buộc ↔ tối thiểu):** trên trang khách, `selection_min` là yếu tố quyết định. Bỏ chọn "Bắt buộc" cho một nhóm trên món sẽ đặt tối thiểu về 0 — và nút "Thêm" hết bị chặn. Bật lại sẽ chặn cho đến khi có lựa chọn.

Ghi đè tối thiểu/tối đa chỉ áp dụng cho món này; món khác có cùng nhóm vẫn dùng
giá trị riêng của nhóm.
