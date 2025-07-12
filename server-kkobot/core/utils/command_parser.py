import re
import core.globals as g


def role_allowed(user_role: str, required_role: str) -> bool:
    hierarchy = {"admin": 3, "owner": 2, "user": 1}
    return hierarchy.get(user_role, 0) >= hierarchy.get(required_role, 0)


def parse_full_input(input_text: str, prefix_map: dict, user_role: str):
    if not input_text:
        return None, {}, ""

    text = input_text.strip()

    # 1. 먼저 명령어 부분만 따로 떼어낸다
    command_type = None
    prompt_text = text

    if text.startswith(">"):
        prompt_candidate = text[1:].strip()  # > 기호 제거 후

        # 명령어 매칭
        matched_type = None
        matched_length = -1

        for prefix, info in prefix_map.items():
            aliases = info.get("aliases", [])
            candidates = [prefix] + aliases

            for candidate in candidates:
                candidate = candidate.strip()
                if candidate.startswith("^"):
                    pattern = re.compile(candidate[1:])
                    if pattern.match(prompt_candidate):
                        if len(candidate) > matched_length:
                            matched_type = info['type']
                            matched_length = len(candidate)
                else:
                    if prompt_candidate.startswith(candidate):
                        if len(candidate) > matched_length:
                            matched_type = info['type']
                            matched_length = len(candidate)

        if matched_type:
            command_type = matched_type
            prompt_text = prompt_candidate

    # 2. 파라미터만 추출 (--key value 형태)
    param_pattern = re.compile(
        r'--(?P<key>\w[\w\-]*)'
        r'(?:=|\s+)'  # = 또는 공백
        r'(?:'
        r'"(?P<dquote_val>[^"]+)"'
        r"|'(?P<squote_val>[^']+)'"
        r'|(?P<plain_val>[^\s]+)'
        r')'
    )

    parameters_all = {}
    matches = list(param_pattern.finditer(prompt_text))

    for match in matches:
        key = match.group('key')
        value = match.group('dquote_val') or match.group('squote_val') or match.group('plain_val')
        if key and value:
            parameters_all[key] = value

    # 3. 파라미터 부분 제거 (prompt 안에서만!)
    prompt_text = param_pattern.sub("", prompt_text).strip()

    # 3-1. 값 없는 플래그 파라미터 처리
    flag_param_pattern = re.compile(r'--(?P<key>\w[\w\-]*)\b')
    flag_matches = list(flag_param_pattern.finditer(prompt_text))
    for match in flag_matches:
        key = match.group('key')
        if key and key not in parameters_all:
            parameters_all[key] = "true"

    # 플래그 파라미터도 제거
    prompt_text = flag_param_pattern.sub("", prompt_text).strip()

    # 4. user_role 기반으로 parameters 필터링
    allowed_params = {}
    if command_type:
        command_definition = g.json_command_data.get(command_type, {})
        allowed_definitions = command_definition.get("parameters", {})

        for param_key, param_value in parameters_all.items():
            param_info = allowed_definitions.get(param_key)
            if not param_info:
                continue
            required_role = param_info.get("required_role", "user")
            if role_allowed(user_role, required_role):
                allowed_params[param_key] = param_value
    else:
        allowed_params = parameters_all

    return command_type, allowed_params, prompt_text
