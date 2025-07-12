def get_restricted_area(board_size, area_type):
    """
    오목판 크기와 영역 타입에 따라 제한 영역 좌표 리스트 반환
    area_type: 'center_only', 'area5x5', 'area7x7'
    반환: [(x, y), ...]
    """
    center = board_size // 2
    if area_type == 'center_only':
        # 중앙 1칸
        return [(center, center)]
    elif area_type == 'area5x5':
        offset = 2
    elif area_type == 'area7x7':
        offset = 3
    else:
        return []
    area = []
    for y in range(center - offset, center + offset + 1):
        for x in range(center - offset, center + offset + 1):
            if 0 <= x < board_size and 0 <= y < board_size:
                area.append((x, y))
    return area 