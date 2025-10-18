#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Lsky Pro 多文件上传API测试脚本
使用方法: python test_multi_upload.py
"""

import requests
import os
import tempfile
from pathlib import Path

class LskyMultiUploadTester:
    def __init__(self, base_url="http://127.0.0.1:8000"):
        self.base_url = base_url
        self.api_endpoint = f"{base_url}/api/v1/upload-multiple"
        self.session = requests.Session()
        self.session.headers.update({
            'Accept': 'application/json',
            'User-Agent': 'LskyMultiUploadTester/1.0'
        })

    def create_test_files(self, count=3):
        """创建测试文件"""
        test_files = []
        temp_dir = tempfile.mkdtemp()

        for i in range(count):
            file_path = os.path.join(temp_dir, f"test_{i+1}.txt")
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(f"Test content {i+1}\nThis is a test file for Lsky Pro multi-upload API.")
            test_files.append(file_path)

        return test_files, temp_dir

    def test_multiple_upload_success(self):
        """测试多文件上传成功"""
        print("1. 测试多文件上传成功...")

        test_files, temp_dir = self.create_test_files(3)

        try:
            files = []
            for file_path in test_files:
                files.append(('files', open(file_path, 'rb')))

            response = self.session.post(self.api_endpoint, files=files)

            print(f"状态码: {response.status_code}")
            print(f"响应: {response.json()}")

            # 关闭文件
            for _, file_obj in files:
                file_obj.close()

            return response.status_code == 200

        except Exception as e:
            print(f"错误: {e}")
            return False
        finally:
            # 清理临时文件
            import shutil
            shutil.rmtree(temp_dir, ignore_errors=True)

    def test_no_files_upload(self):
        """测试无文件上传"""
        print("\n2. 测试无文件上传...")

        try:
            response = self.session.post(self.api_endpoint, json={})
            print(f"状态码: {response.status_code}")
            print(f"响应: {response.json()}")
            return response.status_code == 400
        except Exception as e:
            print(f"错误: {e}")
            return False

    def test_single_file_upload(self):
        """测试单文件上传（兼容性）"""
        print("\n3. 测试单文件上传（兼容性）...")

        test_files, temp_dir = self.create_test_files(1)

        try:
            with open(test_files[0], 'rb') as f:
                files = [('files', f)]
                response = self.session.post(self.api_endpoint, files=files)

            print(f"状态码: {response.status_code}")
            print(f"响应: {response.json()}")
            return response.status_code == 200

        except Exception as e:
            print(f"错误: {e}")
            return False
        finally:
            import shutil
            shutil.rmtree(temp_dir, ignore_errors=True)

    def test_upload_with_auth(self, token=None):
        """测试带认证的上传"""
        print("\n4. 测试带认证的上传...")

        if not token:
            print("跳过: 未提供认证token")
            return True

        test_files, temp_dir = self.create_test_files(2)

        try:
            headers = {'Authorization': f'Bearer {token}'}
            files = []
            for file_path in test_files:
                files.append(('files', open(file_path, 'rb')))

            response = self.session.post(self.api_endpoint, files=files, headers=headers)

            print(f"状态码: {response.status_code}")
            print(f"响应: {response.json()}")
            return response.status_code == 200

        except Exception as e:
            print(f"错误: {e}")
            return False
        finally:
            import shutil
            shutil.rmtree(temp_dir, ignore_errors=True)

    def test_large_file_upload(self):
        """测试大文件上传"""
        print("\n5. 测试大文件上传...")

        # 创建1MB的测试文件
        temp_dir = tempfile.mkdtemp()
        large_file = os.path.join(temp_dir, "large_file.txt")

        try:
            with open(large_file, 'wb') as f:
                f.write(b'0' * 1024 * 1024)  # 1MB

            with open(large_file, 'rb') as f:
                files = [('files', f)]
                response = self.session.post(self.api_endpoint, files=files)

            print(f"状态码: {response.status_code}")
            print(f"响应: {response.json()}")
            return response.status_code in [200, 400]  # 可能因为文件大小限制返回400

        except Exception as e:
            print(f"错误: {e}")
            return False
        finally:
            import shutil
            shutil.rmtree(temp_dir, ignore_errors=True)

    def run_all_tests(self, auth_token=None):
        """运行所有测试"""
        print("=== Lsky Pro 多文件上传API测试 ===")
        print(f"API端点: {self.api_endpoint}")

        tests = [
            ("多文件上传成功", self.test_multiple_upload_success),
            ("无文件上传", self.test_no_files_upload),
            ("单文件上传兼容性", self.test_single_file_upload),
            ("大文件上传", self.test_large_file_upload),
        ]

        if auth_token:
            tests.append(("带认证上传", lambda: self.test_upload_with_auth(auth_token)))

        results = []
        for test_name, test_func in tests:
            try:
                result = test_func()
                results.append((test_name, result))
                print(f"✓ {test_name}: {'通过' if result else '失败'}")
            except Exception as e:
                results.append((test_name, False))
                print(f"✗ {test_name}: 异常 - {e}")

        print("\n=== 测试结果汇总 ===")
        passed = sum(1 for _, result in results if result)
        total = len(results)
        print(f"通过: {passed}/{total}")

        for test_name, result in results:
            status = "✓" if result else "✗"
            print(f"{status} {test_name}")

        return passed == total

if __name__ == "__main__":
    tester = LskyMultiUploadTester()

    # 运行测试（可以传入认证token）
    # tester.run_all_tests(auth_token="your_token_here")
    tester.run_all_tests()
