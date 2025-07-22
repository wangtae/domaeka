# 개발/운영 환경 통일 구조의 장점

## 통일된 구조

### 개발 환경
```
/data/projects/domaeka/
├── dev/                        # 개발 버전 디렉토리
│   ├── domaeka.250719/
│   ├── domaeka.250720/
│   └── domaeka.250721/        # 현재 작업 중
├── domaeka.dev → dev/domaeka.250721
├── current → domaeka.dev
└── docker-compose.yml
```

### 운영 환경
```
/data/projects/domaeka/
├── releases/                   # 릴리즈 버전 디렉토리
│   ├── domaeka.250719/
│   ├── domaeka.250720/
│   └── domaeka.250721/
├── domaeka.test → releases/domaeka.250721
├── domaeka.live → releases/domaeka.250720
├── current → domaeka.test
└── docker-compose.yml
```

## 주요 장점

### 1. 구조적 일관성
- **동일한 패턴**: `폴더/domaeka.날짜/` 형식 통일
- **예측 가능성**: 개발자가 두 환경을 쉽게 이해
- **스크립트 재사용**: 유사한 구조로 스크립트 공유 가능

### 2. 버전 관리 개선
```bash
# 개발 환경에서도 버전 히스토리 유지
dev/
├── domaeka.250719/  # 3일 전 작업
├── domaeka.250720/  # 어제 작업
└── domaeka.250721/  # 오늘 작업
```

### 3. 백업과 복구
- **개발 버전 백업**: 실수로 삭제해도 이전 버전 존재
- **실험적 기능**: 새 버전에서 실험 후 쉽게 롤백
- **병렬 개발**: 여러 기능을 다른 버전에서 개발

### 4. 팀 협업
```bash
# 팀원 A: 기능 A 개발
ln -sfn dev/domaeka.250721-feature-a domaeka.dev

# 팀원 B: 버그 수정
ln -sfn dev/domaeka.250721-bugfix domaeka.dev
```

## 실제 사용 예시

### 새로운 기능 개발 시작
```bash
# 1. 새 개발 버전 생성
./dev-new.sh
# → dev/domaeka.250721/ 생성됨

# 2. 개발 진행
cd domaeka.dev  # → dev/domaeka.250721/로 연결됨
# 코드 작성...

# 3. 테스트
docker compose up -d --build
```

### 이전 버전으로 돌아가기
```bash
# 어제 버전으로 돌아가기
ln -sfn dev/domaeka.250720 domaeka.dev
docker compose restart
```

### 특정 버전 비교
```bash
# 두 버전 간 차이 확인
diff -r dev/domaeka.250720 dev/domaeka.250721
```

## 추가 활용 방안

### 1. 브랜치별 개발
```
dev/
├── domaeka.250721/          # main
├── domaeka.250721-feature-x/  # 기능 X
└── domaeka.250721-hotfix/    # 긴급 수정
```

### 2. 자동 정리 스크립트
```bash
#!/bin/bash
# cleanup-old-dev.sh

# 7일 이상 된 개발 버전 삭제
find dev/ -maxdepth 1 -type d -name "domaeka.*" -mtime +7 -exec rm -rf {} \;
```

### 3. 개발 버전 태깅
```bash
# 중요한 개발 버전 보존
cp -r dev/domaeka.250721 dev/domaeka.250721-stable
```

## 마이그레이션 가이드

### 기존 프로젝트 전환
```bash
# 1. 기존 domaeka.dev 백업
mv domaeka.dev domaeka.dev.backup

# 2. dev 디렉토리 생성
mkdir -p dev

# 3. 백업을 날짜 버전으로 이동
mv domaeka.dev.backup dev/domaeka.$(date +%y%m%d)

# 4. 심볼릭 링크 생성
ln -sfn dev/domaeka.$(date +%y%m%d) domaeka.dev
ln -sfn domaeka.dev current
```

## 결론

이 구조를 사용하면:
1. **일관성**: 개발과 운영이 동일한 구조
2. **안전성**: 모든 버전이 보존됨
3. **유연성**: 쉬운 버전 전환
4. **확장성**: 팀 협업과 병렬 개발 지원

개발 환경에서도 운영 환경과 동일한 수준의 버전 관리가 가능해집니다!