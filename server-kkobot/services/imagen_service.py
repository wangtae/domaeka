"""
Imagen 3 AI 이미지 생성 서비스 모듈
- #이마젠 {프롬프트} 명령어 처리
- sseoba.com API 호출 및 결과 포맷
"""
import httpx
import base64
from core.logger import logger

IMAGEN_API_URL = "https://www.sseoba.com/api/image-generate"
SSEOBA_COOKIE = "sb-hello-auth-token.0=base64-eyJhY2Nlc3NfdG9rZW4iOiJleUpoYkdjaU9pSklVekkxTmlJc0ltdHBaQ0k2SW05MldXdE5hVEJCWkRGWFZsSlBWVU1pTENKMGVYQWlPaUpLVjFRaWZRLmV5SnBjM01pT2lKb2RIUndjem92TDI1MmJYWmpjWHBwZEhGNFptdDJhVzE1Y0dsd0xuTjFjR0ZpWVhObExtTnZMMkYxZEdndmRqRWlMQ0p6ZFdJaU9pSXpaRGhsTW1FellTMHhaamczTFRSaU1tTXRZVGxoWWkweU9XWTNaalppTmpOaE16SWlMQ0poZFdRaU9pSmhkWFJvWlc1MGFXTmhkR1ZrSWl3aVpYaHdJam94TnpRM05qY3dNekEzTENKcFlYUWlPakUzTkRjMk5qWTNNRGNzSW1WdFlXbHNJam9pZDJGdVozUmhaVUJuYldGcGJDNWpiMjBpTENKd2FHOXVaU0k2SWlJc0ltRndjRjl0WlhSaFpHRjBZU0k2ZXlKd2NtOTJhV1JsY2lJNkltZHZiMmRzWlNJc0luQnliM1pwWkdWeWN5STZXeUpuYjI5bmJHVWlYWDBzSW5WelpYSmZiV1YwWVdSaGRHRWlPbnNpWVhaaGRHRnlYM1Z5YkNJNkltaDBkSEJ6T2k4dmJHZ3pMbWR2YjJkc1pYVnpaWEpqYjI1MFpXNTBMbU52YlM5aEwwRkRaemh2WTB0UWRuQktUVU5TU1c1dWIwZFlRbVpoWWpkWmVqWlplbFl5ZEhwck4xUTRUelpGYWpFeFdVczVNek4wZG5oU1NtdGhVbmM5Y3prMkxXTWlMQ0psYldGcGJDSTZJbmRoYm1kMFlXVkFaMjFoYVd3dVkyOXRJaXdpWlcxaGFXeGZkbVZ5YVdacFpXUWlPblJ5ZFdVc0ltWjFiR3hmYm1GdFpTSTZJbGRoYm1kMFlXVWlMQ0pwYzNNaU9pSm9kSFJ3Y3pvdkwyRmpZMjkxYm5SekxtZHZiMmRzWlM1amIyMGlMQ0p1WVcxbElqb2lWMkZ1WjNSaFpTSXNJbkJvYjI1bFgzWmxjbWxtYVdWa0lqcG1ZV3h6WlN3aWNHbGpkSFZ5WlNJNkltaDBkSEJ6T2k4dmJHZ3pMbWR2YjJkc1pYVnpaWEpqYjI1MFpXNTBMbU52YlM5aEwwRkRaemh2WTB0UWRuQktUVU5TU1c1dWIwZFlRbVpoWWpkWmVqWlplbFl5ZEhwck4xUTRUelpGYWpFeFdVczVNek4wZG5oU1NtdGhVbmM5Y3prMkxXTWlMQ0p3Y205MmFXUmxjbDlwWkNJNklqRXdNemswTmpVMk5qVTFNVGc0TXpZNE1USTFNU0lzSW5OMVlpSTZJakV3TXprME5qVTJOalUxTVRnNE16WTRNVEkxTVNKOUxDSnliMnhsSWpvaVlYVjBhR1Z1ZEdsallYUmxaQ0lzSW1GaGJDSTZJbUZoYkRFaUxDSmhiWElpT2x0N0ltMWxkR2h2WkNJNkltOWhkWFJvSWl3aWRHbHRaWE4wWVcxd0lqb3hOelEzTWpJMk56VTFmVjBzSW5ObGMzTnBiMjVmYVdRaU9pSmlNR1E0WkRWaVlTMWpOREEzTFRRek5qTXRZV1l5WVMwMk16SmpOMlJpWVdRek9HWWlMQ0pwYzE5aGJtOXVlVzF2ZFhNaU9tWmhiSE5sZlEuWi1fbWQ5VS1ERjRiOTFxellmd2FEcG5sX1FfUzc5eUctVEdsa2JzREdPSSIsInRva2VuX3R5cGUiOiJiZWFyZXIiLCJleHBpcmVzX2luIjozNjAwLCJleHBpcmVzX2F0IjoxNzQ3NjcwMzA3LCJyZWZyZXNoX3Rva2VuIjoiamlzcW5vbnVod2RyIiwidXNlciI6eyJpZCI6IjNkOGUyYTNhLTFmODctNGIyYy1hOWFiLTI5ZjdmNmI2M2EzMiIsImF1ZCI6ImF1dGhlbnRpY2F0ZWQiLCJyb2xlIjoiYXV0aGVudGljYXRlZCIsImVtYWlsIjoid2FuZ3RhZUBnbWFpbC5jb20iLCJlbWFpbF9jb25maXJtZWRfYXQiOiIyMDI1LTA1LTE0VDEyOjQ1OjU1LjUwMzE3NVoiLCJwaG9uZSI6IiIsImNvbmZpcm1lZF9hdCI6IjIwMjUtMDUtMTRUMTI6NDU6NTUuNTAzMTc1WiIsImxhc3Rfc2lnbl9pbl9hdCI6IjIwMjUtMDUtMTRUMTI6NDU6NTUuODE4NTY5WiIsImFwcF9tZXRhZGF0YSI6eyJwcm92aWRlciI6Imdvb2dsZSIsInByb3ZpZGVycyI6WyJnb29nbGUiXX0sInVzZXJfbWV0YWRhdGEiOnsiYXZhdGFyX3VybCI6Imh0dHBzOi8vbGgzLmdvb2dsZXVzZXJjb250ZW50LmNvbS9hL0FDZzhvY0tQdnBKTUNSSW5ub0dYQmZhYjdZejZZelYydHprN1Q4TzZFajExWUs5MzN0dnhSSmthUnc9czk2LWMiLCJlbWFpbCI6Indhbmd0YWVAZ21haWwuY29tIiwiZW1haWxfdmVyaWZpZWQiOnRydWUsImZ1bGxfbmFtZSI6Ildhbmd0YWUiLCJpc3MiOiJodHRwczovL2FjY291bnRzLmdvb2dsZS5jb20iLCJuYW1lIjoiV2FuZ3RhZSIsInBob25lX3ZlcmlmaWVkIjpmYWxzZSwicGljdHVyZSI6Imh0dHBzOi8vbGgzLmdvb2dsZXVzZXJjb250ZW50LmNvbS9hL0FDZzhvY0tQdnBKTUNSSW5ub0dYQmZhYjdZejZZelYydHprN1Q4TzZFajExWUs5MzN0dnhSSmthUnc9czk2LWMiLCJwcm92aWRlcl9pZCI6IjEwMzk0NjU2NjU1MTg4MzY4MTI1MSIsInN1YiI6IjEwMzk0NjU2NjU1MTg4MzY4MTI1MSJ9LCJpZGVudGl0aWVzIjpbeyJpZGVudGl0eV9pZCI6IjllNzFiM2E1LTNmMDMtNDYwOS05Y2MyLTNiNDBlOWZiYmYxMSIsImlkIjoiMTAzOTQ2NTY2NTUxODgzNjgxMjUxIiwidXNlcl9pZCI6IjNkOGUyYTNhLTFmODctNGIyYy1hOWFiLTI5ZjdmNmI2M2EzMiIsImlkZW50aXR5X2RhdGEiOnsiYXZhdGFyX3Vyb; sb-hello-auth-token.1=CI6Imh0dHBzOi8vbGgzLmdvb2dsZXVzZXJjb250ZW50LmNvbS9hL0FDZzhvY0tQdnBKTUNSSW5ub0dYQmZhYjdZejZZelYydHprN1Q4TzZFajExWUs5MzN0dnhSSmthUnc9czk2LWMiLCJlbWFpbCI6Indhbmd0YWVAZ21haWwuY29tIiwiZW1haWxfdmVyaWZpZWQiOnRydWUsImZ1bGxfbmFtZSI6Ildhbmd0YWUiLCJpc3MiOiJodHRwczovL2FjY291bnRzLmdvb2dsZS5jb20iLCJuYW1lIjoiV2FuZ3RhZSIsInBob25lX3ZlcmlmaWVkIjpmYWxzZSwicGljdHVyZSI6Imh0dHBzOi8vbGgzLmdvb2dsZXVzZXJjb250ZW50LmNvbS9hL0FDZzhvY0tQdnBKTUNSSW5ub0dYQmZhYjdZejZZelYydHprN1Q4TzZFajExWUs5MzN0dnhSSmthUnc9czk2LWMiLCJwcm92aWRlcl9pZCI6IjEwMzk0NjU2NjU1MTg4MzY4MTI1MSIsInN1YiI6IjEwMzk0NjU2NjU1MTg4MzY4MTI1MSJ9LCJwcm92aWRlciI6Imdvb2dsZSIsImxhc3Rfc2lnbl9pbl9hdCI6IjIwMjUtMDUtMTRUMTI6NDU6NTUuNDk5MjM0WiIsImNyZWF0ZWRfYXQiOiIyMDI1LTA1LTE0VDEyOjQ1OjU1LjQ5OTI5MVoiLCJ1cGRhdGVkX2F0IjoiMjAyNS0wNS0xNFQxMjo0NTo1NS40OTkyOTFaIiwiZW1haWwiOiJ3YW5ndGFlQGdtYWlsLmNvbSJ9XSwiY3JlYXRlZF9hdCI6IjIwMjUtMDUtMTRUMTI6NDU6NTUuNDg4NzY2WiIsInVwZGF0ZWRfYXQiOiIyMDI1LTA1LTE5VDE0OjU4OjI3LjU1OTc0N1oiLCJpc19hbm9ueW1vdXMiOmZhbHNlfX0; ph_phc_z9V89hM8MsfJhjWWyLfsJpGMszETah00BC3jCcX1095_posthog=%7B%22distinct_id%22%3A%223d8e2a3a-1f87-4b2c-a9ab-29f7f6b63a32%22%2C%22%24sesid%22%3A%5B1747667240056%2C%220196e90d-a193-779c-96aa-b57279757492%22%2C1747666706835%5D%2C%22%24epp%22%3Atrue%2C%22%24initial_person_info%22%3A%7B%22r%22%3A%22https%3A%2F%2Fcafe.naver.com%2F%22%2C%22u%22%3A%22https%3A%2F%2Fwww.sseoba.com%2Fimage-generator%22%7D%7D"  # 실제 값으로 교체 필요

HEADERS = {
    'authority': 'www.sseoba.com',
    'accept': '*/*',
    'accept-language': 'ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
    'content-type': 'application/json',
    'cookie': SSEOBA_COOKIE,
    'origin': 'https://www.sseoba.com',
    'referer': 'https://www.sseoba.com/image-generator',
    'sec-ch-ua': '"Chromium";v="137", "Not/A)Brand";v="24"',
    'sec-ch-ua-mobile': '?1',
    'sec-ch-ua-platform': '"Android"',
    'sec-fetch-dest': 'empty',
    'sec-fetch-mode': 'cors',
    'sec-fetch-site': 'same-origin',
    'user-agent': 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36'
}

async def download_image_as_base64(url):
    try:
        async with httpx.AsyncClient(timeout=30) as client:
            resp = await client.get(url)
            resp.raise_for_status()
            return base64.b64encode(resp.content).decode('utf-8')
    except Exception as e:
        logger.error(f"[이미지 다운로드 오류] {e}")
        return None

async def handle_imagen_command(context, prompt):
    if not prompt:
        return ["🖼️ Imagen 3 이미지 생성\n\n사용법: #이마젠 (프롬프트)\n예시: #이마젠 은하수를 배경으로 한 늑대 실루엣"]

    payload = {
        "input": {
            "prompt": prompt,
            "width": 888,
            "height": 888,
            "num_outputs": 1,
            "modelId": "google/imagen-3",
            "provider": "replicate"
        }
    }

    try:
        async with httpx.AsyncClient(timeout=120, verify=False) as client:
            resp = await client.post(IMAGEN_API_URL, headers=HEADERS, json=payload)
            resp.raise_for_status()
            data = resp.json()
    except Exception as e:
        logger.error(f"[Imagen3 API 오류] {e}")
        return ["❌ 이미지 생성 중 문제가 발생했습니다. 잠시 후 다시 시도해 주세요!"]

    if data.get("status") == "succeeded" and data.get("output"):
        image_url = data["output"]
        base64_data = await download_image_as_base64(image_url)
        if base64_data:
            return [f"IMAGE_BASE64:{base64_data}"]
        else:
            return [f"🖼️ Imagen 3 이미지 생성 완료!\n\n{image_url}"]
    else:
        return ["❌ 이미지 생성에 실패했습니다."] 