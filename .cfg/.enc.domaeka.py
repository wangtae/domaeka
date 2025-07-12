from cryptography.fernet import Fernet

# 1. 새 프로젝트용 암호화 키 생성 (기존 키 재사용 시 생략)
new_key = Fernet.generate_key()

# 2. 키 저장
with open(".domaeka.key", "wb") as key_file:
    key_file.write(new_key)

# 3. 원본 JSON 파일 읽기
with open(".domaeka.json", "rb") as file:
    data = file.read()

# 4. 데이터 암호화
cipher = Fernet(new_key)
encrypted_data = cipher.encrypt(data)

# 5. 암호화된 데이터를 저장
with open(".domaeka.enc", "wb") as enc_file:
    enc_file.write(encrypted_data)

print("✅ 암호화 완료 → .domaeka.enc 생성")
