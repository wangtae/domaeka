#!/usr/bin/env python3
"""
무한 루프 위험 패턴 탐지 도구

사용법:
    python detect_infinite_loops.py [디렉토리_경로]
"""
import ast
import sys
import pathlib
from typing import List, Dict, Any


class InfiniteLoopDetector(ast.NodeVisitor):
    """AST를 순회하며 무한 루프 위험 패턴을 탐지"""
    
    def __init__(self, filename: str):
        self.filename = filename
        self.warnings = []
        self.current_function = None
        
    def visit_FunctionDef(self, node):
        """함수 정의 방문"""
        old_function = self.current_function
        self.current_function = node.name
        self.generic_visit(node)
        self.current_function = old_function
        
    def visit_AsyncFunctionDef(self, node):
        """비동기 함수 정의 방문"""
        old_function = self.current_function
        self.current_function = node.name
        self.generic_visit(node)
        self.current_function = old_function
        
    def visit_While(self, node):
        """while 루프 검사"""
        # while True: 패턴 검사
        if self._is_while_true(node):
            if not self._has_safe_exit(node):
                self.warnings.append({
                    'file': self.filename,
                    'line': node.lineno,
                    'function': self.current_function,
                    'type': 'while_true_no_exit',
                    'severity': 'high',
                    'message': 'while True without break or proper exit condition'
                })
                
        # 타임아웃 처리 검사
        if self._has_timeout_error_handler(node):
            if not self._has_timeout_retry_limit(node):
                self.warnings.append({
                    'file': self.filename,
                    'line': node.lineno,
                    'function': self.current_function,
                    'type': 'timeout_no_limit',
                    'severity': 'critical',
                    'message': 'TimeoutError handled with continue but no retry limit'
                })
                
        self.generic_visit(node)
        
    def _is_while_true(self, node) -> bool:
        """while True 패턴인지 확인"""
        if isinstance(node.test, ast.Constant):
            return node.test.value is True
        elif isinstance(node.test, ast.NameConstant):  # Python 3.7 호환
            return node.test.value is True
        return False
        
    def _has_safe_exit(self, node) -> bool:
        """안전한 종료 조건이 있는지 확인"""
        for child in ast.walk(node):
            # break 문 확인
            if isinstance(child, ast.Break):
                return True
            # return 문 확인
            if isinstance(child, ast.Return):
                return True
            # shutdown_event 체크 확인
            if isinstance(child, ast.Attribute):
                if hasattr(child.value, 'id') and 'shutdown' in child.value.id.lower():
                    return True
        return False
        
    def _has_timeout_error_handler(self, node) -> bool:
        """TimeoutError 핸들러가 있는지 확인"""
        for child in ast.walk(node):
            if isinstance(child, ast.ExceptHandler):
                if child.type:
                    if isinstance(child.type, ast.Name) and child.type.id == 'TimeoutError':
                        return True
                    if isinstance(child.type, ast.Attribute) and child.type.attr == 'TimeoutError':
                        return True
        return False
        
    def _has_timeout_retry_limit(self, node) -> bool:
        """타임아웃 재시도 제한이 있는지 확인"""
        # 간단한 휴리스틱: 카운터 변수나 break 문이 있는지 확인
        for child in ast.walk(node):
            if isinstance(child, ast.AugAssign):  # +=, -= 등
                if hasattr(child.target, 'id') and 'count' in child.target.id.lower():
                    return True
            if isinstance(child, ast.Compare):  # 비교 연산
                for comparator in child.comparators:
                    if hasattr(comparator, 'id') and 'max' in comparator.id.lower():
                        return True
        return False


def analyze_file(filepath: pathlib.Path) -> List[Dict[str, Any]]:
    """단일 파일 분석"""
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
            
        tree = ast.parse(content)
        detector = InfiniteLoopDetector(str(filepath))
        detector.visit(tree)
        
        return detector.warnings
        
    except Exception as e:
        print(f"Error analyzing {filepath}: {e}")
        return []


def analyze_directory(directory: pathlib.Path) -> Dict[str, Any]:
    """디렉토리 내 모든 Python 파일 분석"""
    all_warnings = []
    files_analyzed = 0
    
    for py_file in directory.rglob('*.py'):
        # 가상환경, 캐시 등 제외
        if any(part in py_file.parts for part in ['venv', '__pycache__', '.git', 'node_modules']):
            continue
            
        warnings = analyze_file(py_file)
        all_warnings.extend(warnings)
        files_analyzed += 1
        
    return {
        'files_analyzed': files_analyzed,
        'total_warnings': len(all_warnings),
        'critical_warnings': len([w for w in all_warnings if w['severity'] == 'critical']),
        'high_warnings': len([w for w in all_warnings if w['severity'] == 'high']),
        'warnings': all_warnings
    }


def print_report(result: Dict[str, Any]):
    """분석 결과 출력"""
    print("\n" + "="*60)
    print("무한 루프 위험 분석 결과")
    print("="*60)
    print(f"분석된 파일 수: {result['files_analyzed']}")
    print(f"총 경고 수: {result['total_warnings']}")
    print(f"  - 치명적(Critical): {result['critical_warnings']}")
    print(f"  - 높음(High): {result['high_warnings']}")
    print()
    
    if result['warnings']:
        print("상세 경고 내용:")
        print("-"*60)
        
        # 심각도별로 정렬
        sorted_warnings = sorted(result['warnings'], 
                               key=lambda x: (x['severity'] != 'critical', x['file']))
        
        for warning in sorted_warnings:
            severity_emoji = "🔴" if warning['severity'] == 'critical' else "🟡"
            print(f"{severity_emoji} [{warning['severity'].upper()}] {warning['file']}:{warning['line']}")
            if warning['function']:
                print(f"   함수: {warning['function']}")
            print(f"   문제: {warning['message']}")
            print()
    else:
        print("✅ 무한 루프 위험 패턴이 발견되지 않았습니다!")


def main():
    """메인 함수"""
    if len(sys.argv) > 1:
        directory = pathlib.Path(sys.argv[1])
    else:
        directory = pathlib.Path.cwd()
        
    if not directory.exists():
        print(f"Error: Directory {directory} does not exist")
        sys.exit(1)
        
    print(f"분석 중: {directory}")
    result = analyze_directory(directory)
    print_report(result)
    
    # 치명적 경고가 있으면 비정상 종료 (CI/CD 연동용)
    if result['critical_warnings'] > 0:
        sys.exit(1)


if __name__ == "__main__":
    main()