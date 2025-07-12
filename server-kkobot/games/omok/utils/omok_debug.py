import traceback
from core.logger import logger

def log_board_state(session, context, tag=""):
    
    return ""

    board = session.board
    board_size = session.board_size if hasattr(session, "board_size") else "?"
    logger.debug(f"[OMOK][DEBUG][{tag}] board_size={board_size}, board 타입={type(board)}, board={board}")
    if isinstance(board, list):
        for idx, row in enumerate(board):
            logger.debug(f"[OMOK][DEBUG][{tag}] board[{idx}] 타입={type(row)}, 값={row}")
    logger.debug(f"[OMOK][DEBUG][{tag}] context={context}")
    logger.debug(f"[OMOK][DEBUG][{tag}] session={session}")
    logger.debug(f"[OMOK][DEBUG][{tag}] call stack:\n{''.join(traceback.format_stack(limit=5))}") 