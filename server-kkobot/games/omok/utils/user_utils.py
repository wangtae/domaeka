def get_user_id(context):
    """
    context에서 user_id(userHash 또는 user_hash)를 추출합니다. 없으면 ValueError를 발생시킵니다.
    """
    user_id = context.get("userHash") or context.get("user_hash")
    if not user_id:
        raise ValueError("[오목] user_id(userHash)가 없습니다. 인증된 사용자만 이용 가능합니다.")
    return user_id


def get_user_name(context):
    """
    context에서 user_name(sender)를 추출합니다. 없으면 빈 문자열 반환.
    """
    return context.get("sender", "") 