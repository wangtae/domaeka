from games.omok.engine.ai_engine import count_open_threes, visualize_pattern

if __name__ == "__main__":
    # 정확한 열린삼 테스트용 바둑판 생성 (세로)
    test_board = [[None]*15 for _ in range(15)]
    test_board[6][7] = 'W'  # (7,6)
    test_board[7][7] = 'W'  # (7,7)
    # (7,5), (7,8), (7,9)는 None (비어있음)

    print("[테스트] 바둑판 상태 (세로):")
    print(visualize_pattern(test_board, [], mark='O'))

    print("\n[테스트] (7,8)에 백돌 착수 후 열린삼 체크 (세로):")
    result = count_open_threes(test_board, 7, 8, 'W', debug=True)
    print('열린삼 개수:', result)

    # 대각선 열린삼 테스트
    diag_board = [[None]*15 for _ in range(15)]
    diag_board[5][5] = 'W'  # (5,5)
    diag_board[6][6] = 'W'  # (6,6)
    # (4,4), (7,7), (8,8)는 None (비어있음)

    print("\n[테스트] 바둑판 상태 (대각선):")
    print(visualize_pattern(diag_board, [], mark='O'))

    print("\n[테스트] (7,7)에 백돌 착수 후 열린삼 체크 (대각선):")
    result_diag = count_open_threes(diag_board, 7, 7, 'W', debug=True)
    print('열린삼 개수:', result_diag) 