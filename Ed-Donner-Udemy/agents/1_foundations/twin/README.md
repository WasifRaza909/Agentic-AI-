---
title: Twin_Agent
emoji: 🤖
colorFrom: blue
colorTo: purple
sdk: gradio
sdk_version: 6.20.0
app_file: app.py
pinned: false
short_description: Chat with my AI digital twin about my career
---

# Digital Twin

A Gradio chat app that answers questions about my career, backed by my LinkedIn
profile and summary. Runs on Hugging Face Spaces (ZeroGPU hardware, CPU-only
workload).

## Environment variables

Set these in the Space under Settings -> Variables and secrets:

| Key                 | Type     | Required | Notes                                        |
| ------------------- | -------- | -------- | -------------------------------------------- |
| `GOOGLE_API_KEY`    | Secret   | yes      | Gemini API key                               |
| `GEMINI_BASE_URL`   | Variable | yes      | Without it the client defaults to OpenAI      |
| `MODEL_CANDIDATES`  | Variable | no       | Comma-separated fallback list                |
| `MODEL_NAME`        | Variable | no       | Preferred model, defaults to first candidate |
| `PUSHOVER_USER`     | Secret   | no       | Needed for the notification tools            |
| `PUSHOVER_TOKEN`    | Secret   | no       | Needed for the notification tools            |

## Running locally

```bash
pip install -r requirements.txt
python app.py
```

`linkedin.pdf` and `summary.txt` must be present -- `context.py` reads both at
import time.
