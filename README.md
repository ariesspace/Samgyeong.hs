# Samgyeong Lightweight School Site

PHP + SQLite 기반의 경량 학교 사이트 골격입니다.

## 실행

```powershell
docker compose up -d --build
```

브라우저에서 `http://localhost:8080`으로 접속합니다.

서버에서 nginx 뒤에 붙일 때는 `.env`에 다음처럼 지정할 수 있습니다.

```env
SAMGYEONG_HTTP_BIND=127.0.0.1
SAMGYEONG_HTTP_PORT=8084
```

## 기본 관리자 계정

- 아이디: `admin`
- 비밀번호: `admin1234`

첫 배포 후 반드시 관리자 비밀번호를 바꾸거나, `app/src/Database.php`의 초기 계정 생성 로직을 수정하세요.

## 구조

- `app/public/index.php`: 진입점 및 라우팅
- `app/src`: DB, 인증, 유틸 함수
- `app/views`: 화면 템플릿
- `app/storage/data`: SQLite DB 볼륨
- `app/storage/uploads`: 업로드 파일
- `docker`: nginx/php-fpm 설정

## 변경 이력

- 2026-07-07: 게시판 첨부 PDF 등 자료 업로드를 위해 nginx/PHP 업로드 제한을 50MB로 상향했습니다.
