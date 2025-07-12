"""
LOA.i 카카오톡 봇 시스템에 블룸버그 크롤러 통합 코드 (분할 발송 버전)
services/bloomberg_service.py 파일로 저장하여 사용
"""

import aiohttp
import asyncio
import json
import os
import re
import random

from bs4 import BeautifulSoup
from datetime import datetime
from typing import Dict, List, Optional, Any

# 코어 모듈 임포트
from core.logger import logger
# from core.globals import room_to_writer  # 레거시 코드 제거

# LLM 서비스 임포트
from services.llm_fallback_service import call_llm_with_fallback

from core import globals as g


class BloombergScraper:
    """
    블룸버그 코리아의 '오늘의 5가지 이슈' 기사를 스크랩하고
    카카오톡 방에 분할하여 발송하는 클래스
    """

    def __init__(self):
        self.blog_url = "https://www.bloomberg.co.kr/blog/"
        self.headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36",
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
            "Accept-Language": "ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7",
            "Referer": "https://www.bloomberg.co.kr/",
            "DNT": "1",
            "Connection": "keep-alive",
            "Upgrade-Insecure-Requests": "1",
            "Sec-Fetch-Dest": "document",
            "Sec-Fetch-Mode": "navigate",
            "Sec-Fetch-Site": "same-origin",
            "Sec-Fetch-User": "?1",
            "Cache-Control": "max-age=0"
        }
        self.timeout = aiohttp.ClientTimeout(total=15)  # 15초 타임아웃
        self.article_data = None  # 파싱된 기사 데이터 저장

    async def fetch_page(self, url: str) -> Optional[str]:
        """웹페이지를 비동기적으로 가져오는 메서드"""
        try:
            async with aiohttp.ClientSession(timeout=self.timeout) as session:
                async with session.get(url, headers=self.headers) as response:
                    if response.status == 200:
                        return await response.text()
                    else:
                        logger.error(f"Error fetching {url}: Status code {response.status}")
                        return None
        except Exception as e:
            logger.error(f"Error during HTTP request: {e}")
            return None

    async def get_latest_issue_url(self) -> Optional[str]:
        """블로그 메인 페이지에서 '오늘의 5가지 이슈'로 시작하는 가장 최신 기사 URL을 찾는 메서드"""
        html = await self.fetch_page(self.blog_url)
        if not html:
            return None

        soup = BeautifulSoup(html, 'html.parser')

        # 모든 기사 링크를 찾음
        article_links = soup.select(".media-body h3.h3-regular-8 a")

        # '오늘의 5가지 이슈'로 시작하는 링크 찾기
        for link in article_links:
            title = link.text.strip()
            if title.startswith(("오늘의 5가지 이슈", "오늘 5가지 이슈")):
                article_url = link.get('href')
                if article_url:
                    logger.info(f"Found latest issue: {title} at {article_url}")
                    return article_url

        logger.warning("No 'Today's 5 Issues' article found")
        return None

    def _extract_article_date(self, soup: BeautifulSoup) -> Optional[str]:
        """기사 날짜를 추출하는 메서드"""
        try:
            date_div = soup.select_one(".article__date .bbg-row--content div")
            if date_div:
                date_text = date_div.text.strip()
                logger.info(f"Extracted article date: {date_text}")
                return date_text
            return None
        except Exception as e:
            logger.error(f"Error extracting date: {e}")
            return None

    def _is_today_article(self, date_str: str) -> bool:
        """기사 날짜가 오늘인지 확인하는 메서드"""
        try:
            # 날짜 형식: YYYY.MM.DD
            article_date = datetime.strptime(date_str, "%Y.%m.%d").date()
            today = datetime.now().date()

            # 실제 운영 시 사용할 코드
            return article_date == today
        except Exception as e:
            logger.error(f"Error parsing date: {e}")
            return False

    def _extract_article_sections(self, soup: BeautifulSoup) -> Dict[str, Any]:
        """기사의 제목, 요약, 본문 섹션을 추출하는 메서드"""
        result = {
            "title": "",
            "summary": "",
            "issues": []
        }

        # 1. 제목 추출
        title_elem = soup.select_one("h1.h1-regular-7")
        if title_elem:
            result["title"] = title_elem.text.strip()

        # 2. 요약 추출
        article_content = soup.select_one(".article__content")

        if article_content:
            # "다음은 시장 참가자들이 관심을 가질 만한 주요 이슈들이다." 문장을 기준으로 요약 추출
            paragraphs = article_content.select("p")

            summary_paragraphs = []
            reached_issues_marker = False

            for p in paragraphs:
                text = p.text.strip()
                if not text:
                    continue

                if "다음은 시장 참가자들이 관심을 가질 만한 주요 이슈들이다" in text:
                    reached_issues_marker = True
                    break

                if "(블룸버그)" in text or len(summary_paragraphs) < 2:
                    summary_paragraphs.append(text)

            result["summary"] = "\n\n".join(summary_paragraphs)

            # 3. h3 태그를 기준으로 이슈 추출 - 가장 직관적이고 안정적인 방법
            h3_tags = article_content.select("h3")
            logger.info(f"Found {len(h3_tags)} h3 tags in the article")

            for h3 in h3_tags:
                # 이미지가 포함된 h3 태그는 건너뛰기
                if h3.find("img"):
                    continue

                title = h3.text.strip()

                # 제목이 없거나 너무 짧으면 건너뛰기
                if not title or len(title) < 3:
                    continue

                content = ""
                next_elem = h3.next_sibling

                # 이 h3와 다음 h3 사이의 모든 p 태그 내용 수집
                while next_elem:
                    if hasattr(next_elem, 'name'):
                        if next_elem.name == 'h3':  # 다음 h3를 만나면 중단
                            break
                        elif next_elem.name == 'p':
                            p_text = next_elem.text.strip()
                            if p_text:
                                if content:
                                    content += "\n\n"
                                content += p_text
                    next_elem = next_elem.next_sibling

                # 내용이 있는 경우만 추가
                if content:
                    result["issues"].append({
                        "title": title,
                        "content": content
                    })
                    logger.info(f"Added issue with title: {title[:30]}...")

        # 만약 h3 태그로 충분한 이슈를 찾지 못한 경우에만 대체 방법 시도
        if len(result["issues"]) < 5:
            logger.warning(f"Only found {len(result['issues'])} issues with h3 tags, trying alternative methods")

            # 텍스트 전체에서 패턴 찾기
            full_text = article_content.get_text(separator="\n", strip=True) if article_content else ""

            # 숫자로 시작하는 단락 패턴 찾기
            issue_pattern = r'(\d+\.\s*[^.\n]{3,100})\s*\n+([^1-5].*?)(?=\n\d+\.\s*|$)'
            matches = re.findall(issue_pattern, full_text, re.DOTALL)

            # 패턴으로 이슈를 찾았다면 기존 이슈를 대체
            if matches and len(matches) >= 3:
                result["issues"] = []  # 기존 이슈 리셋
                for title, content in matches:
                    result["issues"].append({
                        "title": title.strip(),
                        "content": content.strip()
                    })

        # 이슈 순서대로 정렬 (여기서는 h3 태그 순서대로)
        # 강제로 5개 이슈 항목 생성 - 부족한 경우에만
        if len(result["issues"]) < 5:
            current_count = len(result["issues"])
            logger.warning(f"Could only extract {current_count} issues, adding placeholder issues")

            for i in range(current_count, 5):
                result["issues"].append({
                    "title": f"이슈 {i + 1}",
                    "content": "블룸버그 기사에서 추출 중 오류가 발생했습니다. 원문을 참조하세요."
                })

        # 최대 5개 이슈만 사용
        return {
            "title": result["title"],
            "summary": result["summary"],
            "issues": result["issues"][:5]
        }

    def _extract_structured_issues(self, soup: BeautifulSoup) -> List[Dict[str, str]]:
        """구조적인 방법으로 이슈를 추출하는 메서드"""
        issues = []

        # 주요 이슈 헤더들 찾기 (일반적으로 특정 패턴의 h3 태그)
        headers = []
        for h3 in soup.select(".wpb_wrapper h3"):
            text = h3.text.strip()
            if text and len(text.split("…")) > 1:
                headers.append(h3)

        # 각 헤더에 대해 내용 추출
        for i, header in enumerate(headers):
            title = header.text.strip()
            content = ""

            # 다음 헤더까지의 내용 추출
            current = header.next_sibling
            while current and (i == len(headers) - 1 or current != headers[i + 1]):
                if hasattr(current, 'name') and current.name == 'p':
                    content += current.text.strip() + "\n\n"
                current = current.next_sibling

            if title and content:
                issues.append({
                    "title": title,
                    "content": content.strip()
                })

        # 방법 3: h3 태그 내용과 다음 p 태그 그룹 찾기
        if len(issues) < 5:
            all_h3 = soup.select(".wpb_wrapper h3")
            if len(all_h3) >= 5:  # 최소 5개의 h3 태그가 있어야 함
                issues = []
                for h3 in all_h3[:5]:  # 처음 5개만 사용
                    title = h3.text.strip()

                    # 이 h3 다음에 오는 모든 p 태그 찾기
                    content_paras = []
                    next_elem = h3.next_sibling
                    while next_elem:
                        if hasattr(next_elem, 'name'):
                            if next_elem.name == 'h3':  # 다음 h3를 만나면 중단
                                break
                            elif next_elem.name == 'p':
                                content_paras.append(next_elem.text.strip())
                        next_elem = next_elem.next_sibling

                    # p 태그가 발견되지 않으면 다음 p 태그들을 찾기
                    if not content_paras and h3.parent:
                        next_elem = h3.parent.next_sibling
                        while next_elem:
                            if hasattr(next_elem, 'name') and next_elem.name == 'p':
                                content_paras.append(next_elem.text.strip())
                                if len(content_paras) >= 2:  # 최대 2개의 단락만 가져옴
                                    break
                            next_elem = next_elem.next_sibling

                    content = "\n\n".join(content_paras)
                    if title and content:
                        issues.append({
                            "title": title,
                            "content": content
                        })

        # 방법 4: 마지막 방법으로 본문에서 주요 h3 태그와 그 후의 텍스트를 추출
        if len(issues) < 5:
            all_content = soup.select_one(".article__content")
            if all_content:
                # 모든 텍스트 추출
                all_text = all_content.get_text(separator="\n", strip=True)

                # 정규식으로 패턴 찾기 (이슈 제목과 내용)
                patterns = [
                    # 패턴 1: "제목…" 형태
                    r'([^\n]+…)\s*\n+([^\n].*?)(?=\n[^\n]+…|\Z)',
                    # 패턴 2: h3 태그 내용 형태
                    r'([^\n]{5,50})\s*\n+([^\n].*?)(?=\n[^\n]{5,50}\s*\n|\Z)'
                ]

                for pattern in patterns:
                    matches = re.findall(pattern, all_text, re.DOTALL)
                    if len(matches) >= 5:
                        issues = []
                        for title, content in matches[:5]:
                            issues.append({
                                "title": title.strip(),
                                "content": content.strip()
                            })
                        break

        return issues

    async def parse_article(self, article_url: str) -> Optional[Dict[str, Any]]:
        """기사 페이지에서 내용을 파싱하는 메서드"""
        html = await self.fetch_page(article_url)
        if not html:
            return None

        soup = BeautifulSoup(html, 'html.parser')

        # 기사 날짜 확인
        date_str = self._extract_article_date(soup)
        if not date_str:
            logger.error("Could not extract article date")
            return None

        # 오늘 기사인지 확인
        if not self._is_today_article(date_str):
            logger.info(f"Article date {date_str} is not today, skipping")
            return None

        # 기사 내용 추출
        article_sections = self._extract_article_sections(soup)

        # 충분한 이슈를 찾지 못한 경우 경고
        if len(article_sections["issues"]) < 5:
            logger.warning(f"Could only extract {len(article_sections['issues'])} issues instead of 5")

            # 수동 이슈 생성 (부족한 만큼)
            for i in range(len(article_sections["issues"]), 5):
                article_sections["issues"].append({
                    "title": f"이슈 {i + 1}",
                    "content": "이슈 내용을 추출할 수 없습니다."
                })

        # 파싱된 데이터 저장
        self.article_data = {
            "date": date_str,
            "title": article_sections["title"],
            "url": article_url,
            "summary": article_sections["summary"],
            "issues": article_sections["issues"][:5]  # 최대 5개만 사용
        }

        return self.article_data

    def format_for_kakao(self, article_data: Dict[str, Any]) -> List[str]:
        """
        카카오톡 메시지 형식으로 데이터 포맷팅하여 7개 메시지로 분할하는 메서드
        1. 제목 + 링크
        2. 요약
        3~7. 각 이슈별 메시지
        """
        if not article_data:
            return ["최신 블룸버그 이슈를 가져오지 못했습니다."]

        messages = []

        # 1. 제목 + 링크만 포함한 첫 메시지
        title_message = f"📰 {article_data['title']}\n\n"
        title_message += f"🔗 원문 보기: {article_data['url']}"
        messages.append(title_message)

        # 2. 요약만 포함한 두 번째 메시지
        summary_message = f"{article_data['summary']}"
        messages.append(summary_message)

        # 3~7. 각 이슈별 메시지
        for i, issue in enumerate(article_data['issues'], 1):
            if i > 5:  # 최대 5개 이슈
                break

            issue_message = f"📌 {i}. {issue['title']}\n\n"
            issue_message += f"{issue['content']}"
            messages.append(issue_message)

        # 5개 이슈가 안 되는 경우, 빈 메시지로 채우기
        while len(messages) < 7:  # 첫 두 메시지 + 5개 이슈 = 7개
            messages.append(f"⚠️ 이슈 {len(messages) - 2}을 찾을 수 없습니다")

        return messages

    async def create_article_summary(self) -> str:
        """
        파싱된 기사 데이터를 기반으로 LLM을 통해 요약을 생성하는 메서드
        """
        try:
            if not self.article_data:
                logger.warning("[ARTICLE_SUMMARY] No article data available")
                return ""

            # LLM에 전달할 전체 기사 내용 구성
            article_content = f"{self.article_data['title']}\n\n"
            article_content += f"{self.article_data['summary']}\n\n"

            for i, issue in enumerate(self.article_data['issues'], 1):
                article_content += f"이슈 {i}: {issue['title']}\n"
                article_content += f"{issue['content']}\n\n"

            # LLM에 전달할 프롬프트 구성
            prompt = f"""다음은 블룸버그 코리아의 '오늘의 5가지 이슈' 기사 내용입니다. 이 내용을 바탕으로 핵심 요점 5개로 간결하게 요약해주세요:

{article_content}

요약은 5개의 핵심 포인트로 작성하되, 각 포인트는 한 문장으로 간결하게 정리해 주세요."""

            # LLM 호출을 위한 컨텍스트 구성
            context = {
                "bot_name": message.get("bot_name", ""),
                "channel_id": None,
                "user_hash": None,
                "sender": None
            }
            system_prompt = '''
            다음과 같은 형식으로 작성해 주세요. {요약} 이부분이 요약 내용이고 나머지는 템플릿입니다.
            
            📑 기사 요약!
            기사 내용을 바탕으로 핵심 내용을 5가지로 간결하게 요약해 드릴게요. 😊
            
            1. {요약1}
            
            2. {요약2}
            
            3. {요약3}
            
            4. {요약4}
            
            5. {요약5}
            
            궁금한 점이 더 있으시면 언제든 말씀해 주세요! 🌟
            '''

            providers = [
                {
                    "name": "openai",
                    "timeout": 30,
                    "model": "gpt-4o",
                    "retry": 0,
                    "system_prompt": system_prompt
                },
                {
                    "name": "gemini",
                    "model": "gemini-1.5-pro",
                    "timeout": 30,
                    "retry": 0,
                    "system_prompt": system_prompt
                },
                {
                    "name": "grok",
                    "model": "grok-3-latest",
                    "timeout": 30,
                    "retry": 0,
                    "system_prompt": system_prompt
                }
            ]

            # LLM 호출하여 요약 생성
            summary = await call_llm_with_fallback(context, prompt)

            if not summary or summary.startswith("[ERROR]"):
                logger.warning(f"[ARTICLE_SUMMARY] LLM failed to generate summary: {summary}")
                # 기본 요약 제공
                return "\n\n📑 기사 요약:\n• 오늘의 주요 이슈는 통화 정책, 환율 변동, 테슬라 주가 하락, 그리고 글로벌 금융시장 변화입니다."

            # 요약 결과 정리
            return f"\n\n📑 기사 요약:\n\n{summary}"

        except Exception as e:
            logger.error(f"[ARTICLE_SUMMARY_ERROR] 기사 요약 생성 실패: {e}")
            # 오류 발생 시 기본 요약 제공
            return "\n\n📑 기사 요약:\n• 오늘의 주요 이슈는 통화 정책, 환율 변동, 테슬라 주가 하락, 그리고 글로벌 금융시장 변화입니다."

    async def get_latest_issues_split(self, include_summary=False) -> List[str]:
        """
        최신 이슈를 가져와 카카오톡 메시지 형식으로 반환하는 메서드
        include_summary: 마지막 메시지에 기사 요약 포함 여부
        """
        article_url = await self.get_latest_issue_url()
        if not article_url:
            logger.error("최신 블룸버그 이슈를 찾을 수 없습니다.")
            return []

        # 첫 페이지와 기사 페이지 접근 사이에 딜레이 추가
        await asyncio.sleep(random.uniform(3.7, 7.3))  # 2~4초 랜덤 딜레이

        article_data = await self.parse_article(article_url)
        if not article_data:
            logger.error("오늘자 블룸버그 이슈를 찾을 수 없습니다.")
            return []

        messages = self.format_for_kakao(article_data)

        # 마무리 메시지 기본값
        closing_message = "블룸버그 오늘의 5가지 이슈입니다."

        # include_summary가 True일 경우 요약 추가
        if include_summary:
            article_summary = await self.create_article_summary()
            if article_summary:
                closing_message += article_summary

        # 마무리 메시지 추가
        messages.append(closing_message)

        return messages


class BloombergService:
    """
    LOA.i 카카오톡 봇 시스템과 블룸버그 스크래퍼를 연동하는 서비스 클래스
    """

    def __init__(self):
        self.scraper = BloombergScraper()
        self.schedule_path = g.JSON_CONFIG_FILES["schedule_rooms"]

    async def load_schedule_rooms(self) -> Dict[str, Dict[str, Dict]]:
        """스케줄 설정 파일에서 방 정보를 로드하는 메서드"""
        try:
            if os.path.exists(self.schedule_path):
                with open(self.schedule_path, 'r', encoding='utf-8') as f:
                    return json.load(f)
            else:
                logger.error(f"Schedule file not found: {self.schedule_path}")
                return {}
        except Exception as e:
            logger.error(f"Error loading schedule rooms: {e}")
            return {}

    def _get_rooms_for_bloomberg(self, schedule_data: Dict) -> List[Dict[str, str]]:
        """블룸버그 이슈를 발송할 방 목록을 가져오는 메서드"""
        rooms = []
        try:
            for bot_name, bot_data in schedule_data.items():
                for channel_id, channel_data in bot_data.items():
                    # 스케줄 메시지에 "# 블룸버그" 명령어가 있는 방 찾기
                    if "schedules" in channel_data:
                        for schedule in channel_data["schedules"]:
                            messages = schedule.get("messages", [])
                            if any(msg.strip() in ["# 블룸버그", "# 오늘의 이슈", "# 금융뉴스"] for msg in messages):
                                rooms.append({
                                    "bot_name": bot_name,
                                    "channel_id": channel_id,
                                    "room_name": channel_data.get("room_name", "Unknown Room")
                                })
                                break
        except Exception as e:
            logger.error(f"Error getting rooms for Bloomberg: {e}")

        return rooms

    async def send_message_to_room(self, room_info: Dict[str, str], message: str) -> bool:
        try:
            bot_name = room_info["bot_name"]
            channel_id = room_info["channel_id"]
            room_name = room_info.get("room_name", "Unknown")

            logger.info(f"Preparing to send message to {room_name} (channel: {channel_id})")

            # context 구성 (writer는 send_message_response에서 내부적으로 찾음)
            context = {
                'bot_name': bot_name,
                'channel_id': channel_id,
                'room': room_name
            }
            from core.utils.send_message import send_message_response
            await send_message_response(context, message)
            logger.info(f"Bloomberg news sent to {bot_name}/{room_name}")
            return True
        except Exception as e:
            logger.error(f"Error sending message to {room_info.get('room_name', 'Unknown')}: {e}")
            return False

    async def broadcast_bloomberg_news(self, include_summary=True):
        """
        블룸버그 이슈를 등록된 모든 방에 분할하여 발송하는 메서드
        include_summary: 마지막 메시지에 기사 요약 추가 여부
        """
        try:
            # 최신 블룸버그
            messages = await self.scraper.get_latest_issues_split(include_summary=include_summary)
            if not messages or len(messages) == 0 or messages[0].startswith("최신 블룸버그 이슈를 찾을 수 없습니다."):
                logger.warning("No Bloomberg issues found to send")
                return

            # 발송할 방 목록 가져오기
            schedule_data = await self.load_schedule_rooms()
            target_rooms = self._get_rooms_for_bloomberg(schedule_data)

            if not target_rooms:
                logger.warning("No target rooms found for Bloomberg news")
                return

            logger.info(f"Broadcasting Bloomberg news to {len(target_rooms)} rooms")

            # 각 방에 메시지 발송 (메시지당 1초 간격)
            for room in target_rooms:
                logger.info(f"Starting to send messages to room: {room['room_name']}")
                for i, msg in enumerate(messages):
                    logger.info(
                        f"Attempting to send message {i + 1}/{len(messages)} to {room['room_name']}: {msg[:30]}...")
                    success = await self.send_message_to_room(room, msg)
                    logger.info(
                        f"Message {i + 1}/{len(messages)} to {room['room_name']}: {'Success' if success else 'Failed'}")
                    await asyncio.sleep(1)  # 메시지 사이 간격
                logger.info(f"Completed sending messages to room: {room['room_name']}")
                await asyncio.sleep(1)  # 방 사이 간격

            logger.info("Bloomberg news broadcast completed")

        except Exception as e:
            logger.error(f"Error in broadcasting Bloomberg news: {e}")


# LOA.i에 연결할 핸들러 함수
async def handle_bloomberg_news(prompt=None):
    """
    '# 블룸버그' 또는 '# 오늘의 이슈' 명령어 처리 함수
    """
    try:
        scraper = BloombergScraper()
        messages = await scraper.get_latest_issues_split(include_summary=False)

        if not messages or len(messages) == 0:
            logger.error("최신 블룸버그 이슈를 가져오지 못했습니다.")
            return ""

        # 첫 번째 메시지만 반환 (제목 + 요약 + 링크)
        return messages[0] + "\n\n(상세 내용은 채팅방 스케줄 메시지로 발송됩니다.)"
    except Exception as e:
        logger.error(f"Error in bloomberg service: {e}")
        logger.error(f"블룸버그 이슈를 가져오는 중 오류가 발생했습니다. 나중에 다시 시도해주세요.")
        return ""


async def scheduled_send_bloomberg_messages(bot_name, channel_id, room_name, messages):
    """
    블룸버그 메시지들을 순차적으로 스케줄링하여 보내는 함수
    """
    try:
        # core.globals를 직접 임포트
        from core import globals as g

        # 중복 방지를 위한 간단한 추적 메커니즘
        sent_messages = set()

        # 내부 모듈 임포트 - 순환 참조 방지
        from core.utils.send_message import send_message

        # 각 메시지에 대해 지연을 두고 전송
        for i, message in enumerate(messages):
            # 같은 메시지는 한 번만 보내기 (일부 문자열로만 체크)
            message_key = f"{i}_{message[:20]}"
            if message_key in sent_messages:
                logger.info(f"Skipping duplicate message {i + 1}/{len(messages)}")
                continue

            # 각 메시지 발송 사이에 시간 간격 추가
            # 첫 메시지는 빠르게, 나머지는 더 긴 간격으로
            delay = 1.5 if i < 2 else 3
            await asyncio.sleep(delay)

            context = {
                'bot_name': bot_name,
                'channel_id': channel_id,
                'room': room_name
            }
            from core.utils.send_message import send_message_response
            try:
                await send_message_response(context, message)
                sent_messages.add(message_key)
                logger.info(f"Scheduled message {i + 1}/{len(messages)} sent to {room_name}")
            except Exception as e:
                logger.error(f"Failed to send message {i + 1}/{len(messages)}: {e}")
                # 한 번 더 시도
                try:
                    await asyncio.sleep(2)
                    await send_message_response(context, message)
                    sent_messages.add(message_key)
                    logger.info(f"Retry successful for message {i + 1}/{len(messages)}")
                except Exception as retry_e:
                    logger.error(f"Retry also failed for message {i + 1}/{len(messages)}: {retry_e}")

        logger.info(f"Completed sending {len(sent_messages)}/{len(messages)} bloomberg messages to {room_name}")

    except Exception as e:
        logger.error(f"Error in scheduled_send_bloomberg_messages: {e}")
        import traceback
        logger.error(f"Traceback: {traceback.format_exc()}")


# 스케줄 발송 함수 - 스케줄러에서 호출
async def scheduled_bloomberg_news():
    """
    스케줄러를 통해 정해진 시간에 뉴스를 자동으로 발송하는 함수
    """
    service = BloombergService()
    # 기사 요약 포함하여 발송 (기본값: True)
    await service.broadcast_bloomberg_news(include_summary=True)


# 채팅방 명령어 처리 함수 (분할 발송)
async def process_bloomberg_command(received_message):
    """
    '# 블룸버그' 명령어 처리 함수
    첫 번째 메시지 즉시 응답 후 나머지 분할 발송
    """
    try:
        channel_id = received_message.get("channel_id")
        room_name = received_message.get("room")
        bot_name = received_message.get("bot_name", "")

        # 로그 추가
        logger.info(f"Processing Bloomberg command for {bot_name}/{channel_id}/{room_name}")

        # 블룸버그 이슈 가져오기 - 요약 포함
        scraper = BloombergScraper()

        # 최신 기사 URL 가져오기
        article_url = await scraper.get_latest_issue_url()
        if not article_url:
            logger.error("최신 블룸버그 이슈를 찾을 수 없습니다.")
            return ""

        # 기사 내용 파싱
        article_data = await scraper.parse_article(article_url)
        if not article_data:
            logger.error("오늘자 블룸버그 이슈를 찾을 수 없습니다.")
            return ""

        # 메시지 포맷팅
        messages = scraper.format_for_kakao(article_data)

        # 마지막 메시지에 기사 요약 추가
        article_summary = await scraper.create_article_summary()
        closing_message = "블룸버그 오늘의 5가지 이슈입니다."
        if article_summary:
            closing_message += article_summary
        messages.append(closing_message)

        if not messages or len(messages) == 0:
            logger.error("최신 블룸버그 이슈를 가져오지 못했습니다.")
            return ""

        # 메시지 리스트 리턴
        return messages

    except Exception as e:
        logger.error(f"Error in process_bloomberg_command: {e}")
        import traceback
        logger.error(f"Traceback: {traceback.format_exc()}")
        logger.error("블룸버그 이슈를 가져오는 중 오류가 발생했습니다. 나중에 다시 시도해주세요.")
        return ""


async def send_remaining_messages(bot_name, channel_id, room_name, messages):
    """
    나머지 메시지를 순차적으로 발송하는 함수
    """
    try:
        # context 구성 (writer는 send_message_response에서 내부적으로 찾음)
        context = {
            'bot_name': bot_name,
            'channel_id': channel_id,
            'room': room_name
        }
        from core.utils.send_message import send_message_response
        for i, message in enumerate(messages):
            await send_message_response(context, message)
            logger.info(f"Sent follow-up message {i + 1}/{len(messages)} to {room_name}")
            await asyncio.sleep(1.5)  # 메시지 간 지연시간
    except Exception as e:
        logger.error(f"Error sending remaining messages: {e}")
        import traceback
        logger.error(f"Traceback: {traceback.format_exc()}")
