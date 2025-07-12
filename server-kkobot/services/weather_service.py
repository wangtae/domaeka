import httpx
import re
import json
import asyncio
from bs4 import BeautifulSoup
from typing import Dict, List
from core.logger import logger

# naver_weather_service에서 WeatherService 클래스 import
from services.naver_weather_service import WeatherService as NaverWeatherService


class WeatherService:
    def __init__(self):
        # 주요 도시 목록
        self.cities = [
            '서울', '인천', '강릉', '대전', '대구', '포항', '광주',
            '부산', '울산', '제주'
        ]
        # 네이버 날씨 서비스 인스턴스 생성
        self.naver_service = NaverWeatherService()

    async def get_city_weather(self, city: str) -> Dict:
        """
        네이버 날씨 서비스를 사용하여 도시의 날씨 정보를 가져옵니다.
        """
        try:
            # 네이버 날씨 서비스의 search_weather 메서드 호출
            full_weather_info = await self.naver_service.search_weather(city)

            # 필요한 정보만 추출
            # 콘텐츠가 구조화된 문자열이므로 파싱 필요
            weather_info = self._parse_weather_info(full_weather_info)

            return weather_info
        except Exception as e:
            logger.error(f"[WEATHER] {city} 날씨 정보 가져오기 실패: {e}", exc_info=True)
            return {
                'lowest': '정보 없음',
                'highest': '정보 없음',
                'morning_weather': '정보 없음',
                'morning_rain': '0%',
                'afternoon_weather': '정보 없음',
                'afternoon_rain': '0%'
            }

    def _parse_weather_info(self, weather_text: str) -> Dict:
        """
        네이버 날씨 서비스에서 반환한 텍스트에서 필요한 정보를 추출합니다.
        """
        try:
            # 기본 값 설정
            result = {
                'lowest': '정보 없음',
                'highest': '정보 없음',
                'morning_weather': '정보 없음',
                'morning_rain': '0%',
                'afternoon_weather': '정보 없음',
                'afternoon_rain': '0%',
                'morning_emoji': '🌈',
                'afternoon_emoji': '🌈'
            }

            # 최저/최고 온도 추출
            lowest_match = None
            highest_match = None
            for line in weather_text.split('\n'):
                if '최저' in line and '최고' in line:
                    parts = line.split('/')
                    if len(parts) >= 2:
                        lowest_match = parts[0].strip()
                        highest_match = parts[1].strip()
                        break

            if lowest_match:
                result['lowest'] = lowest_match.replace('최저', '').strip()
            if highest_match:
                result['highest'] = highest_match.replace('최고', '').strip()

            # 오전/오후 날씨 추출
            morning_weather_line = None
            for line in weather_text.split('\n'):
                if '오전' in line and '오후' in line and '(' in line:
                    morning_weather_line = line
                    break

            if morning_weather_line:
                parts = morning_weather_line.split('/')
                if len(parts) >= 2:
                    morning_part = parts[0].strip()
                    afternoon_part = parts[1].strip()

                    # 오전 날씨 정보 추출
                    morning_emoji = ''
                    for c in morning_part:
                        if ord(c) > 127:  # 아스키가 아닌 문자(이모지)
                            morning_emoji += c
                    result['morning_emoji'] = morning_emoji if morning_emoji else '🌈'

                    # 오전 강수확률 추출
                    if '(' in morning_part and ')' in morning_part:
                        morning_rain = morning_part[morning_part.find('(') + 1:morning_part.find(')')]
                        result['morning_rain'] = morning_rain

                    # 오후 날씨 정보 추출
                    afternoon_emoji = ''
                    for c in afternoon_part:
                        if ord(c) > 127:  # 아스키가 아닌 문자(이모지)
                            afternoon_emoji += c
                    result['afternoon_emoji'] = afternoon_emoji if afternoon_emoji else '🌈'

                    # 오후 강수확률 추출
                    if '(' in afternoon_part and ')' in afternoon_part:
                        afternoon_rain = afternoon_part[afternoon_part.find('(') + 1:afternoon_part.find(')')]
                        result['afternoon_rain'] = afternoon_rain

            return result
        except Exception as e:
            logger.error(f"[WEATHER] 날씨 정보 파싱 실패: {e}", exc_info=True)
            return {
                'lowest': '정보 없음',
                'highest': '정보 없음',
                'morning_weather': '정보 없음',
                'morning_rain': '0%',
                'afternoon_weather': '정보 없음',
                'afternoon_rain': '0%',
                'morning_emoji': '🌈',
                'afternoon_emoji': '🌈'
            }

    async def get_national_weather(self) -> Dict[str, Dict]:
        """
        전국 주요 도시의 현재 날씨를 크롤링합니다.
        """
        url = "https://www.weather.go.kr/w/weather/forecast/short-term.do"
        weather_data = {}

        try:
            # 기상청에서 날씨 요약 정보 가져오기
            async with httpx.AsyncClient() as client:
                response = await client.get(url)
                response.raise_for_status()

                # BeautifulSoup으로 HTML 파싱
                soup = BeautifulSoup(response.text, 'html.parser')

                # 날씨 요약 정보 추출
                summary_elem = soup.select_one('div.cmp-view-content p.summary')
                full_summary = summary_elem.get_text(strip=True) if summary_elem else ""

                # 오늘, 내일, 모레 정보만 추출
                summary_lines = full_summary.split('○')
                filtered_summary = [
                    line.strip()
                    for line in summary_lines
                    if any(day in line for day in ['(오늘', '(내일'])
                ]
                summary = '\n\n'.join(filtered_summary)

                logger.debug(f"[WEATHER] 추출된 요약: {summary}")

                # 요약 정보 저장
                weather_data['summary'] = summary

            # 각 도시의 날씨 정보를 병렬로 가져오기
            tasks = []
            for city in self.cities:
                tasks.append(self.get_city_weather(city))

            city_weather_results = await asyncio.gather(*tasks)

            # 결과 저장
            for i, city in enumerate(self.cities):
                weather_data[city] = city_weather_results[i]

            logger.debug(f"[WEATHER] 추출된 날씨 정보: {weather_data}")
            return weather_data

        except Exception as e:
            logger.error(f"[WEATHER] 날씨 정보 크롤링 중 오류: {e}", exc_info=True)
            return {'summary': "날씨 정보를 가져오는 중 오류가 발생했습니다."}

    def format_national_weather_message(self, national_weather: Dict[str, Dict]) -> str:
        """
        국가 날씨 정보를 포맷팅된 메시지로 변환합니다.
        """
        if not national_weather:
            return "🌤️ 현재 날씨 정보를 불러올 수 없습니다."

        message = "🌈 오늘의 전국 날씨 🌈\n\n"

        # 요약 정보 추가 (존재할 경우)
        if 'summary' in national_weather and national_weather['summary']:
            message += f"{national_weather['summary']}\n\n"

        # 도시별 날씨 정보
        for city, weather in national_weather.items():
            # 'summary' 키는 건너뛰기
            if city == 'summary':
                continue

            message += f"{city} : "

            try:

                morning_emoji = weather['morning_emoji'].replace('오전', '')
                afternoon_emoji = weather['afternoon_emoji'].replace('오후', '')

                # 최저/최고 온도
                message += f"{weather['lowest']} {morning_emoji}({weather['morning_rain']}) / {weather['highest']} {afternoon_emoji}({weather['afternoon_rain']})\n"
            except KeyError:
                message += "정보를 불러올 수 없습니다.\n"

        message += "\n" + "\u200b" * 500 + "⚠ 네이버 날씨 정보 및 기상청 날씨 정보를 기반으로 제공됩니다."
        return message


async def weather_service():
    """
    날씨 서비스 핸들러 함수
    """
    try:
        logger.info("[WEATHER] 날씨 조회 시작")
        weather_service_instance = WeatherService()
        national_weather = await weather_service_instance.get_national_weather()

        # 디버그용 로깅 추가
        logger.debug(f"[WEATHER] 조회된 날씨 정보: {national_weather}")

        message = weather_service_instance.format_national_weather_message(national_weather)
        logger.info(f"[WEATHER] 생성된 메시지 길이: {len(message)}")
        return message
    except Exception as e:
        logger.error(f"[WEATHER] 날씨 조회 중 오류: {e}", exc_info=True)
        return "🌤️ 현재 날씨 정보를 불러올 수 없습니다."
