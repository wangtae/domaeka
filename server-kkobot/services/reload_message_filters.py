from core.globals import chat_filter_config, MESSAGE_FILTERS_FILE
from core.utils.json_loader import reload_json_file

async def reload_message_filters():
    return await reload_json_file(
        file_path=MESSAGE_FILTERS_FILE,
        target=chat_filter_config,
        description="메시지 필터"
    )
