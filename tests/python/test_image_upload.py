#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Lsky Pro 图片文件批量上传测试脚本
使用方法: python test_image_upload.py
"""

import requests
import os
import tempfile
from pathlib import Path
from PIL import Image
import io

class LskyImageUploadTester:
    def __init__(self, base_url="http://127.0.0.1:8000"):
        self.base_url = base_url
        self.api_endpoint = f"{base_url}/api/v1/upload-multiple"
        self.session = requests.Session()
        self.session.headers.update({
            'Accept': 'application/json',
            'User-Agent': 'LskyImageUploadTester/1.0'
        })

    def create_test_images(self, count=3):
        """创建测试图片文件"""
        test_files = []
        temp_dir = tempfile.mkdtemp()

        for i in range(count):
            # 创建一个简单的测试图片
            img = Image.new('RGB', (100, 100), color=(i*80, 100, 200))

            # 保存为不同格式
            if i == 0:
                file_path = os.path.join(temp_dir, f"test_{i+1}.jpg")
                img.save(file_path, 'JPEG')
            elif i == 1:
                file_path = os.path.join(temp_dir, f"test_{i+1}.png")
                img.save(file_path, 'PNG')
            else:
                file_path = os.path.join(temp_dir, f"test_{i+1}.gif")
                img.save(file_path, 'GIF')

            test_files.append(file_path)

        return test_files, temp_dir

    def test_image_upload_success(self):
        """测试图片文件批量上传成功"""
        print("1. 测试图片文件批量上传成功...")

        test_files, temp_dir = self.create_test_images(3)

        try:
            files = []
            for file_path in test_files:
                files.append(('files[]', open(file_path, 'rb')))

            response = self.session.post(self.api_endpoint, files=files)

            print(f"状态码: {response.status_code}")
            print(f"响应: {response.json()}")

            success = response.status_code == 200 and response.json().get('status', False)
            print(f"✓ 图片文件批量上传成功: {'通过' if success else '失败'}")
            return success

        except Exception as e:
            print(f"✗ 图片文件批量上传成功: 异常 - {e}")
            return False
        finally:
            # 清理临时文件
            for file_path in test_files:
                try:
                    os.unlink(file_path)
                except:
                    pass
            try:
                os.rmdir(temp_dir)
            except:
                pass

    def test_single_image_upload(self):
        """测试单个图片上传"""
        print("2. 测试单个图片上传...")

        test_files, temp_dir = self.create_test_images(1)

        try:
            files = []
            for file_path in test_files:
                files.append(('files[]', open(file_path, 'rb')))

            response = self.session.post(self.api_endpoint, files=files)

            print(f"状态码: {response.status_code}")
            print(f"响应: {response.json()}")

            success = response.status_code == 200 and response.json().get('status', False)
            print(f"✓ 单个图片上传: {'通过' if success else '失败'}")
            return success

        except Exception as e:
            print(f"✗ 单个图片上传: 异常 - {e}")
            return False
        finally:
            # 清理临时文件
            for file_path in test_files:
                try:
                    os.unlink(file_path)
                except:
                    pass
            try:
                os.rmdir(temp_dir)
            except:
                pass

    def run_all_tests(self):
        """运行所有测试"""
        print("=== Lsky Pro 图片文件批量上传测试 ===")
        print(f"API端点: {self.api_endpoint}")

        results = []

        # 测试1: 图片文件批量上传成功
        results.append(self.test_image_upload_success())
        print()

        # 测试2: 单个图片上传
        results.append(self.test_single_image_upload())
        print()

        # 汇总结果
        passed = sum(results)
        total = len(results)

        print("=== 测试结果汇总 ===")
        print(f"通过: {passed}/{total}")

        if passed == total:
            print("🎉 所有测试通过！批量上传功能正常工作！")
        else:
            print("❌ 部分测试失败，请检查日志")

if __name__ == "__main__":
    tester = LskyImageUploadTester()
    tester.run_all_tests()
