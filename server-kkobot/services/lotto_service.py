# ✅ services/lotto_service.py
import random

async def generate_lotto_numbers():
    numbers = sorted(random.sample(range(1, 46), 6))
    bonus = random.randint(1, 45)
    while bonus in numbers:
        bonus = random.randint(1, 45)

    result = (
        f"🎯 이번 주 로또 번호는!\n\n"
        f"✨ 번호: {', '.join(map(str, numbers))}\n"
        f"🎁 보너스: {bonus}"
    )

    print(f"[DEBUG] 로또 번호 결과: {result}")
    return result
