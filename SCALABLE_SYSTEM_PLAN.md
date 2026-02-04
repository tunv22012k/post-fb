# Conceptual Implementation Plan

## 1. Tổng Quan Kiến Trúc & Công Nghệ (Technical Stack)

### A. Đề Xuất Tech Stack (Full-Stack)

*Dưới đây là bộ công nghệ tôi đề xuất dựa trên tính ổn định dài hạn và hiệu suất thực tế cho hệ thống quy mô lớn:*

#### 1. Backend: Laravel 12 + PHP 8.5
*   **Vai trò trong dự án**: Trung tâm điều phối: tiếp nhận bài gốc, phân phối các job AI rewrite song song, và quản lý lịch trình đăng bài (Scheduling) chính xác cho 1000+ điểm đến.
*   **Mục đích sử dụng**: Tận dụng hệ thống **Queue & Horizon** có sẵn để xử lý hàng triệu background job (AI generation, Image processing) mà vẫn đảm bảo tính tuần tự và khả năng hồi phục (retry) khi gặp lỗi mạng.

#### 2. Runtime: Laravel Octane
*   **Vai trò trong dự án**: Gateway chịu tải cao (High-concurrency) để tiếp nhận hàng nghìn Webhook từ Facebook (báo cáo trạng thái bài đăng) cùng một thời điểm.
*   **Mục đích sử dụng**: Loại bỏ độ trễ khởi động (Bootstrapping overhead) của PHP truyền thống, đảm bảo server không bị quá tải khi xử lý hàng loạt request song song trong giờ cao điểm đăng bài.

#### 3. Database: PostgreSQL 18
*   **Vai trò trong dự án**: Lưu trữ cấu hình động của 1000+ Fanpage (Token, Lịch đăng riêng, Prompt riêng) và các biến thể nội dung AI (Unstructured Data).
*   **Mục đích sử dụng**: Sử dụng tính năng **JSONB** để đánh index và truy xuất nhanh các thiết lập đa dạng của từng Page mà không cần thay đổi cấu trúc bảng (Schema) liên tục khi nghiệp vụ thay đổi.

#### 4. Frontend: Next.js (React) hoặc Vue.js
*   **Vai trò trong dự án**: Dashboard theo dõi trực quan trạng thái của hàng nghìn bài đăng và tiến độ xử lý của AI theo thời gian thực (Real-time tracking).
*   **Mục đích sử dụng**: Xây dựng giao diện quản trị phức tạp (Kéo thả lịch, Thống kê multi-page) với trải nghiệm người dùng mượt mà, không cần reload trang khi cập nhật tiến độ Job.

#### 5. Queue System: Redis
*   **Vai trò trong dự án**: Bộ đệm trung gian quản lý toàn bộ các tác vụ nền (Background Jobs).
*   **Mục đích sử dụng**:
    *   **Rate Limiting**: Điều tiết tốc độ post bài tuân thủ chính sách Facebook (200 req/giờ).
    *   **Async Processing (Decoupling)**: Tách rời các tác vụ nặng (AI Rewrite, Image Gen) chạy ngầm để không làm treo Dashboard (Non-blocking UI).
    *   **Reliability**: Đảm bảo an toàn dữ liệu với cơ chế Retry (tự động thử lại khi lỗi mạng) và Dead Letter Queue (lưu trữ job lỗi để xử lý sau).

### B. Quan Hệ Dữ Liệu & Sơ Đồ Database (Detailed Schema)

Để giải quyết bài toán cốt lõi: **"1 input đầu vào (Master) phải biến thành hàng trăm bài đăng unique (Variant) và phân phối chính xác tới hàng nghìn điểm đến (Destination)"**, tôi thiết kế Database gồm 4 thực thể chính.

Mỗi bảng có nhiệm vụ chuyên biệt như sau:

```mermaid
erDiagram
    %% Core Entities
    master_articles ||--|{ content_variants : "generates"
    content_variants ||--|{ publication_jobs : "schedules"
    destination_channels ||--|{ publication_jobs : "executes"

    master_articles {
        bigint id PK
        text raw_content "Input Source (Text/HTML)"
        string source_url "Original Link"
        boolean is_direct_publish "Flag: Skip AI?"
        enum status "PENDING, PROCESSING, COMPLETED"
        timestamp created_at
    }

    content_variants {
        bigint id PK
        bigint master_id FK "References master_articles"
        text final_content "Ready-to-publish Content"
        string source_type "AI_GENERATED / ORIGINAL"
        string tone_label "e.g., Funny, Formal (If AI generated)"
        enum status "WAITING_REVIEW, NEEDS_REVISION, APPROVED, REJECTED"
        json media_assets "Image/Video URLs"
        timestamp created_at
    }

    destination_channels {
        bigint id PK
        string platform "Top-level: Facebook, WordPress"
        text credentials "Encrypted Access Tokens"
        string setting_group "Grouping: News, Ent, Store"
    }

    publication_jobs {
        bigint id PK
        bigint variant_id FK
        bigint channel_id FK
        enum status "QUEUED, PUBLISHED, FAILED"
        timestamp scheduled_at "Execution Time"
        string platform_response_id "External Post ID"
    }
```

#### 1. Bảng `master_articles` (Table chứa bài gốc)
*   **Mục đích**: Lưu trữ dữ liệu thô đầu vào ("Center of Truth"). Dù sau này có sửa đổi biến thể thế nào, bài gốc vẫn được giữ nguyên để đối chiếu.
*   **Chi tiết các năng nhiệm vụ từng cột**:
    *   `id` (PK): Định danh duy nhất cho bài gốc.
    *   `raw_content` (Text): Chứa toàn bộ nội dung text hoặc HTML do người dùng nhập vào. Đây là nguyên liệu chính cho AI xử lý.
    *   `source_url` (String): Lưu URL gốc để hệ thống tự động cào nội dung (Crawl), ngăn chặn user nhập trùng bài đã có, và phục vụ việc ghi nguồn bản quyền khi cần thiết.
    *   `is_direct_publish` (Boolean): **Cờ quyết định luồng xử lý**.
        *   `true`: Hệ thống sẽ copy `raw_content` sang bảng Variants ngay lập tức (Bỏ qua AI).
        *   `false`: Hệ thống sẽ đẩy bài vào hàng đợi AI để viết lại thành nhiều bản.
    *   `status` (Enum): Trạng thái xử lý của bài gốc (`PENDING`=Mới tạo, `PROCESSING`=Đang chạy AI, `COMPLETED`=Đã sinh xong các biến thể).

#### 2. Bảng `content_variants` (Table chứa biến thể nội dung)
*   **Mục đích**: Chuẩn hóa dữ liệu đầu ra. Đây là bảng mà Worker đăng bài sẽ đọc dữ liệu. Nó chứa cả bài do AI viết và bài gốc (nếu chọn Direct Publish).
*   **Chi tiết các năng nhiệm vụ từng cột**:
    *   `id` (PK): Định danh biến thể.
    *   `master_id` (FK): Khóa ngoại trỏ về `master_articles`. Giúp gom nhóm: "1 bài gốc này đã đẻ ra 10 biến thể nào?".
    *   `final_content` (Text): Nội dung cuối cùng (Clean Text/HTML) sẵn sàng để gửi lên API Facebook/WordPress. Worker sẽ lấy cột này để post.
    *   `source_type` (Enum): Đánh dấu nguồn gốc (`AI_GENERATED` hoặc `ORIGINAL`). Dùng để Analytics so sánh hiệu quả tương tác giữa bài người viết và bài AI viết.
    *   `tone_label` (String): Nhãn giọng văn (ví dụ: "funny", "professional"). Dùng để routing (định tuyến): Bài "funny" sẽ ưu tiên đăng lên các Page thuộc nhóm Giải trí.
    *   `status` (Enum): Trạng thái duyệt (`WAITING_REVIEW`, `NEEDS_REVISION`, `APPROVED`, `REJECTED`). Quyết định bài nào được phép chuyển sang hàng đợi Scheduling.
    *   `media_assets` (JSON): Chứa mảng các đường dẫn ảnh/video đính kèm. Ví dụ: `['https://s3.../img1.jpg', '...']`. Dữ liệu này đã được module xử lý ảnh (Watermark/Resize) chuẩn hóa.
    *   `created_at` (Timestamp): Thời điểm biến thể được tạo ra.

#### 3. Bảng `destination_channels` (Table chứa list kênh phân phối)
*   **Mục đích**: Quản lý danh tính và quyền truy cập của 1000+ endpoints (Page, Website) mà không phụ thuộc vào code.
*   **Các cột quan trọng & Ý nghĩa**:
    *   `platform_type` (Enum: `fb_page`, `wordpress_site`): Định danh loại nền tảng để Backend biết phải dùng Driver nào (FacebookService hay WordPressService) để post bài.
    *   `credentials` (Encrypted Text): Chứa Access Token hoặc API Key. **Bắt buộc mã hóa** để bảo mật.
    *   `category_tag` (String): Gắn thẻ cho Page (ví dụ: 'network_A', 'network_B'). Giúp User chọn nhanh "Đăng lên tất cả Page thuộc Network A" thay vì tick chọn 500 lần.

#### 4. Bảng `publication_jobs` (Table trung gian điều phối)
*   **Mục đích cốt lõi**: Giải quyết bài toán **"Multiplexing"** (1 biến thể nội dung nhân bản ra N điểm đến). Đây là nơi biến 1 lệnh "Đăng bài" thành 1000 tasks nhỏ độc lập.
*   **Tại sao bảng này quan trọng?**:
    *   **Cô lập lỗi (Fault Isolation)**: Nếu đăng lên Page A bị lỗi (do token chết), chỉ record của Page A bị đánh dấu `FAILED`. Các Page B, C, D vẫn chạy bình thường. Nếu không có bảng này, 1 lỗi nhỏ sẽ làm sập cả dây chuyền.
    *   **Tránh Timeout (Asynchronous)**: Thay vì vòng lặp `foreach` 1000 lần (gây treo server), hệ thống tạo 1000 dòng `queued` và để Worker nhặt ra xử lý dần dần (Rate Limiting).
    *   **Lưu vết (Tracking)**: Cột `platform_response_id` lưu lại ID bài viết thực tế trên Facebook (VD: `123456789_987654321`). Dùng để sau này Bot có thể quay lại trả lời comment hoặc lấy số liệu Like/Share.
*   **Các cột quan trọng & Ý nghĩa**:
    *   `variant_id` & `channel_id` (FK): Xác định chính xác "Biến thể nội dung nào" sẽ đăng lên "Kênh nào".
    *   `status` (`queued`, `publishing`, `published`, `failed`): Quản lý vòng đời của từng post riêng lẻ. Nếu post lên Page A lỗi -> Chỉ dòng này `failed`, các Page B, C vẫn chạy bình thường.
    *   `platform_response_id`: Lưu ID bài viết trả về từ Facebook/Web (để sau này bot vào comment hoặc lấy thống kê view/like).
    *   `scheduled_at`: Thời gian dự kiến đăng (cho phép dời lịch từng post riêng lẻ dù chung chiến dịch).

---

## 2. Chiến Lược Phân Phối & API (Distribution Strategy)

### A. Tích hợp Hệ Sinh Thái Meta (Facebook)
*   **Quản Lý Token (Lifecycle Management)**:
    *   **Quy trình Onboarding**:
        1.  User "Login" -> Hệ thống nhận Short-lived Token (2 giờ).
        2.  **Exchange**: Hệ thống gọi API đổi sang Long-lived User Token (60 ngày).
        3.  **Fetch Pages**: Dùng Token 60 ngày đó gọi `/me/accounts`. **Kết quả**: Tất cả Page Token trả về sẽ là **Vĩnh Viễn** (Never Expire).
    *   **Cơ chế Auto-Refresh & Alert**: Job định kỳ quét hàng ngày. Nếu Token còn sống < 7 ngày -> Tự động gọi API gia hạn. Nếu chết/mất quyền -> Báo động đỏ (Alert) về Dashboard hoặc thông báo bên ngoài (Slack, Zalo,…) để Admin kết nối lại thủ công.
    *   **Bảo mật**: Chỉ lưu Token đã mã hóa (Encrypted) trong Database.
*   **Chiến Thuật Post Bài (Rate Limiting)**:
    *   Không được post 400 bài cùng 1 giây (Facebook sẽ khóa App).
    *   Sử dụng **Redis Rate Logic**: Giới hạn mỗi App chỉ được gọi API 200 req/giờ (hoặc theo quota của Facebook).
    *   Chia Batch: Mỗi phút chỉ nhả ra khoảng 20-50 bài post rải đều các Page khác nhau.
*   **Quy Trình Xử Lý Đa Phương Tiện (Media Handling)**:
    *   Hệ thống phân luồng dữ liệu thông minh: Các bài đăng chứa Ảnh/Video sẽ được đẩy qua hàng đợi ưu tiên (Priority Queue) để upload asset lấy `media_id` trước, đảm bảo khi gọi API `feed`, mọi tài nguyên đã sẵn sàng. Tránh lỗi upload timeout phổ biến khi file quá nặng.
*   **Giám Sát Trạng Thái Real-time (Webhook Monitoring)**:
    *   Thay vì treo connection chờ API phản hồi (gây nghẽn server), hệ thống sẽ hoạt động theo cơ chế "Fire-and-Forget" và lắng nghe **Meta Webhooks**. Khi Facebook xử lý xong video hoặc đăng bài thành công, họ sẽ bắn tín hiệu về endpoint của ta, giúp cập nhật trạng thái `Published` trên Dashboard tức thì.

### B. Đăng Bài Đa Nền Tảng (Multi-Domain)
*   **Kiến trúc Driver/Adapter (Design Pattern)**:
    *   Sử dụng **Adapter Pattern** để mở rộng không giới hạn các loại web đích. Hệ thống lõi gọi `CodeInterface->publish()`, còn các class con (`WordPressAdapter`, `CustomWebAdapter`) sẽ tự lo phần logic riêng.
    *   **WordPress**: Tận dụng REST API kết hợp "Application Passwords". Adapter tự động map các field (Title, Content, Author, Category) vào endpoint tương ứng.
    *   **Custom Web**: Gửi Webhook chứa payload JSON chuẩn hóa.
*   **Chiến lược Đảm bảo Hiển thị (Formatting Consistency)**:
    *   **Content Transformer**: Thay vì lưu HTML cứng, lưu nội dung dưới dạng **Markdown** hoặc **JSON Blocks**. Khi publish, Adapter sẽ convert sang định dạng đích (VD: WP nhận HTML Blocks, Web React nhận JSON raw).
    *   **Asset Management (CDN)**: Để ảnh hiển thị đúng trên mọi domain, toàn bộ ảnh trong bài viết được host tại **Central S3/CDN**. Trong nội dung bài gửi đi chỉ chứa Absolute URL (Ví dụ: `https://cdn.mysystem.com/img1.jpg`), tránh việc ảnh bị lỗi 404 do relative path.

### C. AI Content Factory (Quy trình sản xuất)
Luồng dữ liệu sẽ đi như một dây chuyền nhà máy:

1.  **Input**: Người dùng ném 1 link bài báo hoặc 1 chủ đề.
2.  **Phase 1 - AI Rewrite Dispatcher (Ensuring Uniqueness)**:
    *   **Logic**: Hệ thống kích hoạt 5-10 Workers xử lý song song. Mỗi worker áp dụng một loại người dùng khác nhau (KOL, Chuyên gia, GenZ).
    *   **Kỹ thuật tránh Duplicate Content**: Sử dụng tham số `temperature: 0.8` và yêu cầu AI thay đổi cấu trúc câu (Active/Passive voice), bộ từ đồng nghĩa (Synonyms) để đảm bảo độ độc nhất ngôn ngữ (Linguistic Uniqueness) > 90%.
3.  **Phase 2 - Image Enhancement**:
    *   **Upscale/Generate**: Sau khi có Text, Worker phân tích từ khóa để gọi DALL-E 3 vẽ ảnh bìa mới HOẶC dùng AI Upscaler (Real-ESRGAN) để làm nét ảnh cũ.
    *   **Watermark**: Tự động đóng dấu logo của từng Page lên ảnh để "chiếm hữu" bản quyền.
4.  **Phase 3 - Staging & Approval**:
    *   Mọi bài viết sinh ra sẽ ở trạng thái `WAITING_REVIEW`.
    *   Giao diện Dashboard cho phép User xem trước (Preview) trên giả lập giao diện Facebook/Web. User có thể sửa nhanh tại chỗ trước khi bấm "Release to Queue".

---

## 3. Vận Hành Ổn Định & Mở Rộng (Reliability)

### A. Xử lý Hàng Đợi (Queue & Failures)
Hệ thống sử dụng mô hình **Distributed Queue (Redis)** để chịu tải hàng triệu tasks.

*   **Retry Thông Minh (Exponential Backoff)**:
    *   Nếu Facebook sập (Lỗi 500/503) khi đang post 1000 bài thì không retry ngay lập tức (tránh spam API). Hệ thống sẽ đợi theo cấp số nhân: 1 phút, 5 phút, 15 phút, 1 giờ. Nếu vẫn lỗi -> Đẩy vào **Dead Letter Queue (DLQ)** để Admin kiểm tra sau.
*   **Cơ chế Ngắt Mạch (Circuit Breaker)**:
    *   Nếu phát hiện tỷ lệ lỗi > 30% liên tiếp cho một Page/API cụ thể, hệ thống tự động dừng Queue cho kênh đó trong 30 phút. Việc này ngăn chặn việc lãng phí tài nguyên và bảo vệ tài khoản khỏi bị Facebook đánh dấu spam do lỗi liên tục.
*   **Monitoring (Laravel Horizon)**:
    *   Dashboard theo dõi thời gian thực: Tốc độ xử lý (Throughput), Job thất bại. Tự động Auto-scale số lượng Workers đưa vào lượng job tồn đọng (Queue backlog).

### B. Giám Sát Dành Cho Người Dùng (Non-Technical Monitoring)
*   **Xây dựng giao diện Quản lý Bài Viết**:
    *   Xây dựng màn hình tập trung để Admin dễ dàng kiểm soát tình hình bài viết của toàn bộ các Pages.
    *   Sử dụng mã màu trạng thái bài viết (Xanh=Thành công, Vàng=Đang xử lý, Đỏ=Lỗi) giúp nhận diện rủi ro nếu có lỗi.
    *   Hiển thị danh sách các Page bị ngắt kết nối với nút bấm **"Reconnect Facebook"** để sửa lỗi nhanh.
*   **Thông báo lỗi**:
    *   Biểu tượng chuông báo trên góc màn hình: "Bài viết #123 thất bại trên 5 Page. Lý do: Ảnh quá khổ".
    *   **Email Report**: Gửi báo cáo tổng hợp vào 8:00 sáng mỗi ngày cho Quản lý ("Hôm qua: 980 bài thành công, 20 bài lỗi").
*   **Kênh Kỹ thuật**:
    *   Tích hợp Zalo/Slack,... Bot để báo lỗi hệ thống 500/Timeout cho đội Dev.

### C. Bảo Mật (Security - The Vault)
*   **Mã Hóa Đa Lớp (Encryption at Rest)**:
    *   Sử dụng Laravel Encrypter (AES-256-CBC) để mã hóa toàn bộ Token trong Database.
    *   **Key Rotation**: Khóa giải mã `APP_KEY` không lưu cứng trong code, mà được inject qua biến môi trường (Environment Variable) an toàn trên Server.
    *   **Enterprise Option (Advanced)**: Đối với các dự án yêu cầu bảo mật cấp cao (Banking/Enterprise), hệ thống sẵn sàng tích hợp **AWS Secrets Manager** hoặc **HashiCorp Vault** để quản lý vòng đời của API Keys thay vì lưu trực tiếp trong Database, đảm bảo tuân thủ các tiêu chuẩn bảo mật khắt khe nhất.
*   **Phân Quyền (RBAC)**:
    *   Chỉ user có quyền `SUPER_ADMIN` mới được phép xem danh sách Token hoặc kết nối Page mới.
    *   Nhân viên viết bài (Editor) chỉ thấy tên Page, hoàn toàn không tiếp cận được Token gốc.
*   **Audit Logging**:
    *   Ghi lại mọi lịch sử truy cập nhạy cảm: "Ai vừa export danh sách token?", "Ai vừa xóa Page X?". Giúp truy vết sai phạm (Accountability).

---

## 4. Sản phẩm bàn giao (Deliverables)

### A. Mô Tả Luồng Đi Dữ Liệu (Visual Workflow)
*Hành trình chi tiết của bài viết với các cơ chế rẽ nhánh và vòng lặp duyệt:*

1.  **Input (Draft)**: User khởi tạo bài viết hoặc nhập link báo. Tại đây có quyết định rẽ nhánh:
    *   *Nhánh A (Direct)*: Không dùng AI, chuyển thẳng xuống Staging chờ duyệt.
    *   *Nhánh B (AI Mode)*: Chuyển sang "Content Factory".
2.  **Factory Processing (Nếu chọn AI)**:
    *   Hệ thống sinh song song các biến thể (Hài hước, Nghiêm túc, Tóm tắt).
    *   Sinh ảnh/Video tương ứng.
    *   Tự động chuyển kết quả về Staging.
3.  **Review Loop (Vòng lặp duyệt)**: Tại Staging, Admin xem xét các biến thể:
    *   *Action: Edit*: Admin tự chỉnh sửa câu từ -> Lưu lại (Vẫn ở Staging).
    *   *Action: Request Changes*: Gửi yêu cầu chỉnh sửa cho User -> Bài viết về trạng thái `User Revision` -> User sửa xong gửi lại Staging.
    *   *Action: Reject*: Bài viết bị đánh dấu "Hủy" và kết thúc luồng.
    *   *Action: Approve*: Xác nhận nội dung OK -> Chuyển sang bước chọn Lịch & Kênh (Scheduling) -> Trạng thái `QUEUED`.
4.  **Distribution Queue (Hàng đợi)**:
    *   Hệ thống quét các job `QUEUED` đã đến giờ hẹn.
    *   Thực hiện gửi API sang Facebook/Website.
5.  **Completion**:
    *   Hệ thống nhận Webhook từ Platform.
    *   Nếu Thành công -> Dashboard hiện **Xanh** (Published).
    *   Nếu Lỗi -> Dashboard hiện **Đỏ** (Failed) và kích hoạt cơ chế Retry.

```mermaid
stateDiagram-v2
    direction LR
    
    %% Khởi tạo
    state "User Input" as Input
    [*] --> Input
    
    %% Nút quyết định rẽ nhánh
    state "Có dùng AI không?" as Decision <<choice>>
    Input --> Decision
    
    %% Nhánh xử lý
    Decision --> Staging: Không (Direct)
    Decision --> AI_Factory: Có (AI Mode)
    
    state "Content Factory" as AI_Factory {
        Gen_Text --> Gen_Image: Tạo Prompt ảnh
        Gen_Image --> Staging: Hoàn tất
    }

    %% Vòng lặp duyệt bài
    state "Review Zone (Staging)" as Review {
        Staging --> Admin_Action
        
        state "Admin Decision" as Admin_Action <<choice>>
        Admin_Action --> Edit_Mode: Admin tự sửa
        Edit_Mode --> Staging: Lưu thay đổi

        Admin_Action --> Request_Changes: Yêu cầu User sửa
        Request_Changes --> Staging: User gửi lại
        
        Admin_Action --> Rejected: Từ chối (End)
        Admin_Action --> Queued: Duyệt (Approve)
    }

    %% Khu vực phân phối
    state "Distribution System" as Dist {
        Queued --> Dispatching: Đến giờ (Cron)
        Dispatching --> Published: Webhook 200 OK
        Dispatching --> Failed: Lỗi API
        Failed --> Queued: Auto Retry
    }

    %% Trạng thái kết thúc
    state "Hoàn tất & Lưu trữ" as Finish
    Published --> Finish
    Rejected --> Finish
```

### B. Danh Sách Công Cụ & Dịch Vụ (Tooling & Services)
*   **Infrastructure**: AWS EC2 (Server), AWS RDS (Database), AWS S3 (Lưu ảnh), Redis (Queue).
*   **AI Services**: OpenAI GPT, Gemini (Viết bài), Midjourney/DALL-E 3 (Vẽ ảnh).
*   **Libraries (Laravel)**:
    *   `laravel/horizon`: Quản lý Queue UI.
    *   `spatie/laravel-permission`: Quản lý phân quyền (RBAC).
    *   `facebook/graph-sdk`: Driver kết nối Meta API.
*   **Facebook App Permissions (Required)**:
    *   `pages_manage_posts`, `pages_read_engagement`: Để đăng bài và xem comment.
    *   `pages_show_list`: Để thực hiện **Bulk Onboarding** (Lấy danh sách Page).
    *   `business_management`: Để quản lý Business Assets (Nếu cần).

### C. Lộ Trình Triển Khai Chi Tiết (Detailed Roadmap)

#### Phase 1: Foundation & MVP - "Xây Móng & Luồng Đơn"
*Mục tiêu: Chạy được luồng cơ bản nhất "Input -> Publish" trên 1 Page.*
*   **System Design & Setup**
    *   **Planning**: Chốt yêu cầu, vẽ luồng nghiệp vụ (Workflow).
    *   **Design**: Vẽ ERD Database, Wireframe UI Dashboard.
    *   **Setup**: Khởi tạo Repo (Laravel + Next.js/Vue.js), Cài đặt Docker, Redis, DB cấu trúc.
*   **Core Development (Backend + Frontend)**
    *   Implement Authentication (Login/Register).
    *   CRUD Bài viết gốc (Master Articles).
    *   Implement kết nối Facebook Login (Lấy Short Token).
*   **Basic Publication Flow**
    *   Xây dựng Adapter `FacebookService` cơ bản (Post text/image).
    *   Chạy thử luồng: User tạo bài -> Bấm Post -> Hiện lên Fanpage (Synchronous - chưa có Queue).
*   **Testing & MVP Handover**
    *   Test luồng Happy Path. Fix bugs cơ bản.
    *   Demo xem thử flow đăng bài thủ công.

#### Phase 2: Automation & Content Factory - "Tự Động Hóa & Luồng Phức"
*Mục tiêu: Tích hợp AI và xử lý bất đồng bộ (Async).*
*   **AI Integration**
    *   Tích hợp OpenAI API: Viết Prompt engineer để rewrite bài.
    *   Tích hợp Image Gen (DALL-E/Midjourney).
*   **Queue & Job System (Core Engine)**
    *   Chuyển luồng Post từ Sync sang **Async (Redis Queue)**.
    *   Tách bảng `publication_jobs`. Xây dựng Worker xử lý nền.
*   **Review Workflow (Staging)**
    *   Xây dựng giao diện duyệt bài (Approve/Reject/Edit).
    *   Xử lý rẽ nhánh logic: AI vs Direct Publish.
*   **Advanced Features**
    *   Multi-channel: Tích hợp thêm WordPress Adapter.
    *   Scheduler: Tính năng hẹn giờ đăng bài (Cron jobs).

#### Phase 3: Scaling & Reliability - "Mở Rộng & Ổn Định"
*Mục tiêu: Chịu tải 1000 Pages và Xử lý lỗi.*
*   **Scalability Features**
    *   Implement **Bulk Onboarding** (Quét `/me/accounts`).
    *   Implement **Token Exchange** (Đổi token 60 ngày).
*   **Reliability & Optimization**
    *   Cấu hình **Rate Limiting** (Redis Throttling).
    *   Cài đặt **Circuit Breaker** và **Exponential Backoff Retry**.
*   **Monitoring & Security**
    *   Dựng Dashboard theo dõi lỗi trực quan (Traffic Light).
    *   Security Audit: Mã hóa Token, phân quyền RBAC, check lỗ hổng.
*   **Stress Test & Handover**
    *   Giả lập 1000 jobs chạy cùng lúc để test tải Server.
    *   Tối ưu Query SQL, Caching.
    *   Bàn giao source code và tài liệu vận hành.
