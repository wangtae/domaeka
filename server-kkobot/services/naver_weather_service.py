import aiohttp
from bs4 import BeautifulSoup
import re
import asyncio
from typing import Dict, Tuple, Optional, List


class WeatherService:
    def __init__(self):
        # 날씨 상태에 따른 이모티콘 매핑
        self.weather_emoji = {
            # 맑음 관련
            "맑음": "☀️",
            "대체로 맑음": "🌤️",
            "밝음": "☀️",
            "해": "☀️",
            "화창": "🌞",

            # 구름 관련
            "구름조금": "🌤️",
            "구름많음": "⛅",
            "구름많고": "⛅",
            "흐림": "☁️",
            "흐리고": "☁️",
            "구름": "☁️",

            # 비 관련
            "비": "🌧️",
            "흐리고 비": "🌧️",
            "흐리고 가끔 비": "🌧️",
            "흐리고 한때 비": "🌧️",
            "구름많고 비": "🌧️",
            "구름많고 가끔 비": "🌧️",
            "구름많고 한때 비": "🌦️",
            "소나기": "🌦️",
            "가끔 소나기": "🌦️",

            # 눈 관련
            "눈": "❄️",
            "흐리고 눈": "🌨️",
            "흐리고 가끔 눈": "🌨️",
            "흐리고 한때 눈": "🌨️",
            "구름많고 눈": "🌨️",
            "구름많고 가끔 눈": "🌨️",
            "구름많고 한때 눈": "🌨️",

            # 비/눈 혼합
            "비/눈": "🌨️",
            "눈/비": "🌨️",
            "흐리고 비/눈": "🌨️",
            "구름많고 비/눈": "🌨️",

            # 천둥/번개 관련
            "천둥번개": "⛈️",
            "뇌우": "⛈️",
            "비/번개": "⛈️",
            "흐리고 뇌우": "⛈️",
            "구름많고 뇌우": "⛈️",

            # 기타 날씨 현상
            "안개": "🌫️",
            "옅은 안개": "🌫️",
            "짙은 안개": "🌫️",
            "황사": "🌫️",
            "연무": "🌫️",
            "태풍": "🌀",
            "폭풍": "🌪️",
            "우박": "🌧️",

            # 계절 특화 문구
            "폭염": "🔥",
            "한파": "❄️"
        }

    def get_weather_emoji(self, weather_text: str) -> str:
        """날씨 텍스트에서 적절한 이모티콘을 찾아 반환합니다."""
        for key, emoji in self.weather_emoji.items():
            if key in weather_text:
                return emoji
        return "🌈"  # 기본 이모티콘

    async def search_weather(self, location: str) -> str:
        """주어진 위치의 날씨 정보를 검색합니다."""
        try:
            # 네이버 검색 URL 구성
            url = f"https://search.naver.com/search.naver?query=날씨+{location}"

            # 비동기 HTTP 요청
            async with aiohttp.ClientSession() as session:
                async with session.get(url) as response:
                    if response.status != 200:
                        return f"날씨 정보를 가져오는 데 실패했습니다. (상태 코드: {response.status})"

                    html = await response.text()

            # BeautifulSoup으로 파싱
            soup = BeautifulSoup(html, 'html.parser')

            # 현재 위치 정보 추출
            try:
                location_info = soup.select_one('.title_area._area_panel h2.title')
                current_location = location_info.text.strip() if location_info else location
            except:
                current_location = location

            # 현재 온도
            try:
                current_temp_elem = soup.select_one('.temperature_text')
                current_temp = current_temp_elem.text.strip() if current_temp_elem else "정보 없음"
            except:
                current_temp = "정보 없음"

            fixed_temp = re.sub(r'(온도)(?!\s)(-?\d)', r'\1 \2', current_temp)

            # 주간 예보 리스트에서 오늘 날씨 정보 파싱
            today_weather = {}

            try:
                # 첫 번째 week_item을 찾음 (오늘 날씨)
                today_item = soup.select_one('.week_list .week_item')

                if today_item:
                    # 날짜 정보
                    day_elem = today_item.select_one('.day')
                    date_elem = today_item.select_one('.date')

                    if day_elem and date_elem:
                        today_weather['date'] = f"{day_elem.text} {date_elem.text}"
                    else:
                        today_weather['date'] = "오늘"

                    # 오전 날씨
                    morning_rainfall = today_item.select('.weather_inner')[0].select_one('.rainfall')
                    morning_weather = today_item.select('.weather_inner')[0].select_one('.wt_icon .blind')

                    if morning_rainfall and morning_weather:
                        today_weather['morning_rain'] = morning_rainfall.text
                        today_weather['morning_weather'] = morning_weather.text
                    else:
                        today_weather['morning_rain'] = "0%"
                        today_weather['morning_weather'] = "정보 없음"

                    # 오후 날씨
                    afternoon_rainfall = today_item.select('.weather_inner')[1].select_one('.rainfall')
                    afternoon_weather = today_item.select('.weather_inner')[1].select_one('.wt_icon .blind')

                    if afternoon_rainfall and afternoon_weather:
                        today_weather['afternoon_rain'] = afternoon_rainfall.text
                        today_weather['afternoon_weather'] = afternoon_weather.text
                    else:
                        today_weather['afternoon_rain'] = "0%"
                        today_weather['afternoon_weather'] = "정보 없음"

                    # 최저/최고 온도 (blind 태그 제외하고 추출)
                    try:
                        lowest = "정보 없음"
                        highest = "정보 없음"

                        lowest_elem = today_item.select_one('.lowest')
                        highest_elem = today_item.select_one('.highest')

                        if lowest_elem:
                            # blind 클래스를 제외하고 텍스트만 추출
                            blind_elem = lowest_elem.select_one('.blind')
                            if blind_elem:
                                blind_elem.extract()  # blind 부분 제거
                            lowest = lowest_elem.text.strip()

                        if highest_elem:
                            # blind 클래스를 제외하고 텍스트만 추출
                            blind_elem = highest_elem.select_one('.blind')
                            if blind_elem:
                                blind_elem.extract()  # blind 부분 제거
                            highest = highest_elem.text.strip()

                        today_weather['lowest'] = lowest
                        today_weather['highest'] = highest
                    except:
                        today_weather['lowest'] = "정보 없음"
                        today_weather['highest'] = "정보 없음"
            except Exception as e:
                today_weather = {
                    'date': "오늘",
                    'morning_rain': "0%",
                    'morning_weather': "정보 없음",
                    'afternoon_rain': "0%",
                    'afternoon_weather': "정보 없음",
                    'lowest': "정보 없음",
                    'highest': "정보 없음"
                }

            # 어제와 비교 정보 (이모티콘 추가)
            try:
                comparison_text = "정보 없음"
                comparison_emoji = ""

                compare_elem = soup.select_one('.temperature_info .summary')
                if compare_elem:
                    # 전체 텍스트 가져오기
                    full_text = compare_elem.text.strip()

                    # 어제보다 온도 차이 부분 추출
                    temp_diff_elem = compare_elem.select_one('.temperature')

                    if temp_diff_elem:
                        # 높아요/낮아요 확인
                        is_up = 'up' in temp_diff_elem.get('class', [])
                        is_down = 'down' in temp_diff_elem.get('class', [])

                        # blind 클래스 제거
                        blind_elem = temp_diff_elem.select_one('.blind')
                        if blind_elem:
                            blind_elem.extract()

                        temp_diff = temp_diff_elem.text.strip()

                        # 이모티콘 추가
                        if is_up:
                            comparison_emoji = "🔺"  # 상승 이모티콘
                        elif is_down:
                            comparison_emoji = "🔻"  # 하강 이모티콘

                        comparison_text = f"어제보다 {temp_diff} {comparison_emoji}"
            except:
                comparison_text = "정보 없음"
                comparison_emoji = ""

            # 체감 온도, 습도, 바람 정보
            try:
                # 체감 온도
                feel_temp = "정보 없음"
                humidity = "정보 없음"
                wind = "정보 없음"

                summary_items = soup.select('.temperature_info .summary_list .sort')
                for item in summary_items:
                    term = item.select_one('.term')
                    desc = item.select_one('.desc')

                    if term and desc:
                        if '체감' in term.text:
                            feel_temp = desc.text.strip()
                        elif '습도' in term.text:
                            humidity = desc.text.strip()
                        elif '풍' in term.text:
                            wind = desc.text.strip()
            except:
                feel_temp = "정보 없음"
                humidity = "정보 없음"
                wind = "정보 없음"

            # 미세먼지, 초미세먼지, 자외선, 일출/일몰 정보
            try:
                fine_dust = "정보 없음"
                ultra_fine_dust = "정보 없음"
                uv = "정보 없음"
                sunrise = "정보 없음"
                sunset = "정보 없음"

                chart_items = soup.select('.today_chart_list .item_today')
                for item in chart_items:
                    title = item.select_one('.title')
                    txt = item.select_one('.txt')

                    if title and txt:
                        if '미세먼지' in title.text and '초미세' not in title.text:
                            fine_dust = txt.text.strip()
                        elif '초미세먼지' in title.text:
                            ultra_fine_dust = txt.text.strip()
                        elif '자외선' in title.text:
                            uv = txt.text.strip()
                        elif '일출' in title.text:
                            sunrise = txt.text.strip()
                        elif '일몰' in title.text:
                            sunset = txt.text.strip()
            except:
                fine_dust = "정보 없음"
                ultra_fine_dust = "정보 없음"
                uv = "정보 없음"
                sunrise = "정보 없음"
                sunset = "정보 없음"

            # 시간별 날씨 정보 추출 (HTML 구조 순서대로 추출)
            hourly_forecasts = []

            try:
                # 정확히 시간별 온도 데이터가 있는 위치에서 추출
                hourly_items = soup.select('.graph_inner._hourly_weather ul li')

                # 강수확률 데이터
                rain_probs = {}
                rain_items = soup.select('.precipitation_graph_box .icon_wrap li.data')
                time_items = soup.select('.precipitation_graph_box .time_wrap li.time')

                if len(rain_items) == len(time_items):
                    for i, time_item in enumerate(time_items):
                        time_text = time_item.text.strip()
                        value_elem = rain_items[i].select_one('.value')
                        if value_elem and value_elem.text.strip() != '-':
                            rain_probs[time_text] = value_elem.text.strip()

                # 습도 데이터
                humidity_data = {}
                humidity_items = soup.select('.humidity_graph_box .graph_wrap li.data')
                humidity_time_items = soup.select('.humidity_graph_box .time_wrap li.time')

                if len(humidity_items) == len(humidity_time_items):
                    for i, time_item in enumerate(humidity_time_items):
                        time_text = time_item.text.strip()
                        num_elem = humidity_items[i].select_one('.num')
                        if num_elem:
                            humidity_data[time_text] = f"{num_elem.text.strip()}%"

                # 시간별 날씨 데이터 추출 (순서 그대로 12개)
                count = 0
                for item in hourly_items:
                    # 날짜 구분자인 경우 건너뛰기
                    if 'tomorrow' in item.get('class', []) or 'after_tomorrow' in item.get('class', []):
                        continue

                    # 시간 추출
                    time_elem = item.select_one('.time em')
                    if not time_elem:
                        continue

                    time_text = time_elem.text.strip()

                    # 온도 추출
                    temp_elem = item.select_one('.degree_point .num')
                    temp_text = "정보 없음"
                    if temp_elem:
                        temp_text = temp_elem.text.strip()

                    # 날씨 상태 추출
                    weather_elem = item.select_one('.weather_box .wt_icon .blind')
                    weather_text = "정보 없음"
                    if weather_elem:
                        weather_text = weather_elem.text.strip()

                    # 강수확률 및 습도 추가
                    rain_rate = "0%"
                    if time_text in rain_probs:
                        rain_rate = rain_probs[time_text]

                    humidity_value = humidity
                    if time_text in humidity_data:
                        humidity_value = humidity_data[time_text]

                    # 시간별 날씨 데이터 추가
                    hourly_forecasts.append({
                        'time': time_text,
                        'temp': temp_text,
                        'weather': weather_text,
                        'emoji': self.get_weather_emoji(weather_text),
                        'rain_rate': rain_rate,
                        'humidity': humidity_value
                    })

                    count += 1
                    if count >= 9:  # 처음 12개 항목만 표시
                        break

            except Exception as e:
                hourly_forecasts = []  # 오류 시 빈 목록으로 초기화

            # 주간 날씨 예보 추출
            weekly_forecasts = []
            try:
                # 내일부터 시작하는 주간 예보 항목들
                weekly_items = soup.select('.week_list .week_item')[1:9]  # 최대 4일치만 (오늘 제외)

                for item in weekly_items:
                    weekly_data = {}

                    # 요일 및 날짜
                    day_elem = item.select_one('.day')
                    date_elem = item.select_one('.date')

                    if day_elem and date_elem:
                        weekly_data['date'] = f"{day_elem.text} {date_elem.text}"
                    else:
                        weekly_data['date'] = "정보 없음"

                    # 오전 날씨
                    try:
                        morning_weather_elem = item.select('.weather_inner')[0].select_one('.wt_icon .blind')
                        morning_rain_elem = item.select('.weather_inner')[0].select_one('.rainfall')

                        if morning_weather_elem:
                            weather_status = morning_weather_elem.text.strip()
                            weekly_data['morning_weather'] = weather_status
                            weekly_data['morning_emoji'] = self.get_weather_emoji(weather_status)
                        else:
                            weekly_data['morning_weather'] = "정보 없음"
                            weekly_data['morning_emoji'] = "🌈"

                        if morning_rain_elem:
                            weekly_data['morning_rain'] = morning_rain_elem.text.strip()
                        else:
                            weekly_data['morning_rain'] = "0%"
                    except:
                        weekly_data['morning_weather'] = "정보 없음"
                        weekly_data['morning_emoji'] = "🌈"
                        weekly_data['morning_rain'] = "0%"

                    # 오후 날씨
                    try:
                        afternoon_weather_elem = item.select('.weather_inner')[1].select_one('.wt_icon .blind')
                        afternoon_rain_elem = item.select('.weather_inner')[1].select_one('.rainfall')

                        if afternoon_weather_elem:
                            weather_status = afternoon_weather_elem.text.strip()
                            weekly_data['afternoon_weather'] = weather_status
                            weekly_data['afternoon_emoji'] = self.get_weather_emoji(weather_status)
                        else:
                            weekly_data['afternoon_weather'] = "정보 없음"
                            weekly_data['afternoon_emoji'] = "🌈"

                        if afternoon_rain_elem:
                            weekly_data['afternoon_rain'] = afternoon_rain_elem.text.strip()
                        else:
                            weekly_data['afternoon_rain'] = "0%"
                    except:
                        weekly_data['afternoon_weather'] = "정보 없음"
                        weekly_data['afternoon_emoji'] = "🌈"
                        weekly_data['afternoon_rain'] = "0%"

                    # 최저/최고 온도
                    try:
                        lowest_elem = item.select_one('.lowest')
                        highest_elem = item.select_one('.highest')

                        if lowest_elem:
                            blind_elem = lowest_elem.select_one('.blind')
                            if blind_elem:
                                blind_elem.extract()
                            weekly_data['lowest'] = lowest_elem.text.strip()
                        else:
                            weekly_data['lowest'] = "정보 없음"

                        if highest_elem:
                            blind_elem = highest_elem.select_one('.blind')
                            if blind_elem:
                                blind_elem.extract()
                            weekly_data['highest'] = highest_elem.text.strip()
                        else:
                            weekly_data['highest'] = "정보 없음"
                    except:
                        weekly_data['lowest'] = "정보 없음"
                        weekly_data['highest'] = "정보 없음"

                    weekly_forecasts.append(weekly_data)
            except Exception as e:
                weekly_forecasts = []  # 오류 시 빈 목록으로 초기화

            # 결과 메시지 구성
            result = f"🌈 {current_location} 날씨 🌈\n\n"
            # result += f"📅 {today_weather['date']}\n\n"

            # 현재 온도와 어제 대비 온도 차이
            result += f"{fixed_temp} ({comparison_text})\n"

            # 최저/최고 온도
            result += f"최저 {today_weather['lowest']} / 최고 {today_weather['highest']}\n"

            # 오전/오후 날씨와 이모티콘
            morning_emoji = self.get_weather_emoji(today_weather['morning_weather'])
            afternoon_emoji = self.get_weather_emoji(today_weather['afternoon_weather'])

            result += f"오전 {morning_emoji}({today_weather['morning_rain']}) / "
            result += f"오후 {afternoon_emoji}({today_weather['afternoon_rain']})\n\n"

            # 체감 온도, 습도, 바람 등 추가 정보
            result += f"🌡️ 체감 온도: {feel_temp}\n"
            if humidity != "정보 없음":
                result += f"💧 습도: {humidity}\n"
            if wind != "정보 없음":
                result += f"💨 바람: {wind}\n"

            result += f"😷 미세/초미세먼지: {fine_dust} | {ultra_fine_dust}\n"
            if uv != "정보 없음":
                result += f"🕶️ 자외선: {uv}\n"

            # 일출/일몰 시간 표시 (둘 다 있으면 둘 다 표시)
            if sunrise != "정보 없음" and sunset != "정보 없음":
                result += f"🌅 일출: {sunrise}\n"
                result += f"🌇 일몰: {sunset}\n"
            elif sunrise != "정보 없음":
                result += f"🌅 일출: {sunrise}\n"
            elif sunset != "정보 없음":
                result += f"🌇 일몰: {sunset}\n"

            # 시간별 날씨 정보 추가
            if hourly_forecasts:
                result += "\n📊 시간별 날씨 (비 확률, 습도)\n"
                for forecast in hourly_forecasts:
                    # 시간, 이모티콘, 온도, 강수확률, 습도 순으로 표시
                    humidity_info = ""
                    if 'humidity' in forecast and forecast['humidity'] != "정보 없음":
                        humidity_info = f", {forecast['humidity']}"

                    result += f"{forecast['time']} {forecast['emoji']} {forecast['temp']} ({forecast['rain_rate']}{humidity_info})\n"

            # 주간 날씨 예보 추가
            if weekly_forecasts:
                result += "\n📆 주간 날씨 예보\n"
                for forecast in weekly_forecasts:
                    result += f"[{forecast['date']}] {forecast['lowest']}～{forecast['highest']}, "
                    result += f"오전 {forecast['morning_emoji']}({forecast['morning_rain']}) / "
                    result += f"오후 {forecast['afternoon_emoji']}({forecast['afternoon_rain']})\n"

            result += "\n" + "\u200b" * 500 + "⚠️ 네이버 날씨와 기상청 정보를 기반으로 제공됩니다."

            return result

        except Exception as e:
            return f"🧭 어디라고요...? 지도에서 찾을 수 없어요! 지역명을 확인해 주세요.\n예: # 날씨 서울"


async def handle_weather_command(prompt: str) -> str:
    """
    날씨 명령어 처리 함수

    사용 예:
    # 날씨 서울
    # 날씨 부산
    # 날씨 런던
    """
    # 전처리: 입력 문자열 앞뒤 공백 제거
    location = prompt.strip()

    # 위치가 비어있는 경우
    if not location:
        return "위치를 입력해주세요.\n사용법: # 날씨 [위치]\n예: # 날씨 서울"

    # 날씨 서비스 인스턴스 생성 및 검색 실행
    weather_service = WeatherService()
    result = await weather_service.search_weather(location)

    return result
