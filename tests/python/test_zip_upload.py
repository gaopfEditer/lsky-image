#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Lsky Pro ZIP文件上传测试脚本
使用方法: python test_zip_upload.py
"""

import requests
import os
import tempfile
import zipfile
from pathlib import Path
from PIL import Image
import io

class LskyZipUploadTester:
    def __init__(self, base_url="http://127.0.0.1:8000"):
        self.base_url = base_url
        self.api_endpoint = f"{base_url}/api/v1/upload-zip"
        self.session = requests.Session()
        self.session.headers.update({
            'Accept': 'application/json',
            'User-Agent': 'LskyZipUploadTester/1.0'
        })

    def create_test_zip(self, image_count=3):
        """创建包含测试图片的ZIP文件"""
        # 创建临时目录
        temp_dir = tempfile.mkdtemp()
        zip_path = os.path.join(temp_dir, 'test_images.zip')

        # 创建ZIP文件
        with zipfile.ZipFile(zip_path, 'w') as zip_file:
            for i in range(image_count):
                # 创建测试图片
                img = Image.new('RGB', (100, 100), color=(i*80, 100, 200))

                # 保存为不同格式
                if i == 0:
                    img_name = f"test_{i+1}.jpg"
                    img_path = os.path.join(temp_dir, img_name)
                    img.save(img_path, 'JPEG')
                elif i == 1:
                    img_name = f"test_{i+1}.png"
                    img_path = os.path.join(temp_dir, img_name)
                    img.save(img_path, 'PNG')
                else:
                    img_name = f"test_{i+1}.gif"
                    img_path = os.path.join(temp_dir, img_name)
                    img.save(img_path, 'GIF')

                # 添加到ZIP文件
                zip_file.write(img_path, img_name)

                # 删除临时图片文件
                os.unlink(img_path)

        return zip_path, temp_dir

    def test_zip_upload_success(self):
        """测试ZIP文件上传成功"""
        print("1. 测试ZIP文件上传成功...")

        zip_path, temp_dir = self.create_test_zip(3)

        try:
            with open(zip_path, 'rb') as f:
                files = {'zip_file': f}
                response = self.session.post(self.api_endpoint, files=files)

            print(f"状态码: {response.status_code}")
            print(f"响应: {response.json()}")

            success = response.status_code == 200 and response.json().get('status', False)
            print(f"✓ ZIP文件上传成功: {'通过' if success else '失败'}")
            return success

        except Exception as e:
            print(f"✗ ZIP文件上传成功: 异常 - {e}")
            return False
        finally:
            # 清理临时文件
            try:
                os.unlink(zip_path)
                os.rmdir(temp_dir)
            except:
                pass

    def test_zip_upload_with_strategy(self):
        """测试带strategy_id的ZIP文件上传"""
        print("2. 测试带strategy_id的ZIP文件上传...")

        zip_path, temp_dir = self.create_test_zip(2)

        try:
            with open(zip_path, 'rb') as f:
                files = {'zip_file': f}
                data = {'strategy_id': '1'}  # 假设strategy_id为1
                response = self.session.post(self.api_endpoint, files=files, data=data)

            print(f"状态码: {response.status_code}")
            print(f"响应: {response.json()}")

            success = response.status_code == 200 and response.json().get('status', False)
            print(f"✓ 带strategy_id的ZIP文件上传: {'通过' if success else '失败'}")
            return success

        except Exception as e:
            print(f"✗ 带strategy_id的ZIP文件上传: 异常 - {e}")
            return False
        finally:
            # 清理临时文件
            try:
                os.unlink(zip_path)
                os.rmdir(temp_dir)
            except:
                pass

    def test_zip_upload_invalid_format(self):
        """测试无效格式的ZIP文件上传"""
        print("3. 测试无效格式的ZIP文件上传...")

        # 创建一个非ZIP文件
        temp_dir = tempfile.mkdtemp()
        fake_zip_path = os.path.join(temp_dir, 'fake.zip')

        with open(fake_zip_path, 'w') as f:
            f.write("This is not a ZIP file")

        try:
            with open(fake_zip_path, 'rb') as f:
                files = {'zip_file': f}
                response = self.session.post(self.api_endpoint, files=files)

            print(f"状态码: {response.status_code}")
            print(f"响应: {response.json()}")

            # 应该返回错误
            success = response.status_code == 200 and not response.json().get('status', True)
            print(f"✓ 无效格式ZIP文件上传: {'通过' if success else '失败'}")
            return success

        except Exception as e:
            print(f"✗ 无效格式ZIP文件上传: 异常 - {e}")
            return False
        finally:
            # 清理临时文件
            try:
                os.unlink(fake_zip_path)
                os.rmdir(temp_dir)
            except:
                pass

    def test_zip_upload_no_images(self):
        """测试不包含图片的ZIP文件上传"""
        print("4. 测试不包含图片的ZIP文件上传...")

        # 创建只包含文本文件的ZIP
        temp_dir = tempfile.mkdtemp()
        zip_path = os.path.join(temp_dir, 'no_images.zip')

        with zipfile.ZipFile(zip_path, 'w') as zip_file:
            for i in range(2):
                text_name = f"text_{i+1}.txt"
                text_path = os.path.join(temp_dir, text_name)
                with open(text_path, 'w') as f:
                    f.write(f"This is text file {i+1}")
                zip_file.write(text_path, text_name)
                os.unlink(text_path)

        try:
            with open(zip_path, 'rb') as f:
                files = {'zip_file': f}
                response = self.session.post(self.api_endpoint, files=files)

            print(f"状态码: {response.status_code}")
            print(f"响应: {response.json()}")

            # 应该返回错误
            success = response.status_code == 200 and not response.json().get('status', True)
            print(f"✓ 不包含图片的ZIP文件上传: {'通过' if success else '失败'}")
            return success

        except Exception as e:
            print(f"✗ 不包含图片的ZIP文件上传: 异常 - {e}")
            return False
        finally:
            # 清理临时文件
            try:
                os.unlink(zip_path)
                os.rmdir(temp_dir)
            except:
                pass

    def run_all_tests(self):
        """运行所有测试"""
        print("=== Lsky Pro ZIP文件上传测试 ===")
        print(f"API端点: {self.api_endpoint}")

        results = []

        # 测试1: ZIP文件上传成功
        results.append(self.test_zip_upload_success())
        print()

        # 测试2: 带strategy_id的ZIP文件上传
        results.append(self.test_zip_upload_with_strategy())
        print()

        # 测试3: 无效格式的ZIP文件上传
        results.append(self.test_zip_upload_invalid_format())
        print()

        # 测试4: 不包含图片的ZIP文件上传
        results.append(self.test_zip_upload_no_images())
        print()

        # 汇总结果
        passed = sum(results)
        total = len(results)

        print("=== 测试结果汇总 ===")
        print(f"通过: {passed}/{total}")

        if passed == total:
            print("🎉 所有测试通过！ZIP文件上传功能正常工作！")
        else:
            print("❌ 部分测试失败，请检查日志")

if __name__ == "__main__":
    tester = LskyZipUploadTester()
    tester.run_all_tests()
