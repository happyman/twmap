# Mapsforge 地圖更新腳本

自動檢查、下載並部署 RudyMap 與 Happyman 地圖更新的工具。

## 檔案說明

| 檔案 | 用途 |
|------|------|
| `rudymap_download.py` | 檢查 RudyMap 新版本，下載地圖檔、清理 tile cache、清除 Cloudflare cache |
| `happyman_download.py` | 檢查 Happyman 新版本，下載地圖檔、清理 tile cache |
| `config.py` | **設定檔**（已 gitignore，不會上傳），存放 API key、路徑等隱私資料 |
| `config.example.py` | 設定檔範本，提交用 |

## 使用方式

```bash
# 檢查並更新
python3 rudymap_download.py
python3 happyman_download.py

# 只檢查版本不更新
python3 rudymap_download.py -v
python3 happyman_download.py -v
```

## 初次使用

1. 複製設定檔：`cp config.example.py config.py`
2. 編輯 `config.py`，填入你的實際路徑、網域、API key

## 原理

- 定時輪詢遠端 JSON API 取得最新版本號
- 與本地 `VERSION` 檔案比對，發現新版本即自動下載、解壓縮、清理快取
- 更新完成後發送 Email 通知
- RudyMap 另會清除 Cloudflare cache
