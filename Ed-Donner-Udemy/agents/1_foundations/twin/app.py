from openai import OpenAI
from context import TWIN_SYSTEM_PROMPT
from tools import tools, handle_tool_calls
from styles import CSS, JS, EXAMPLES
from dotenv import load_dotenv
import gradio as gr
import os

load_dotenv(override=True)

MODEL_CANDIDATES = [
    model.strip()
    for model in os.environ.get("MODEL_CANDIDATES", "gemini-3.1-flash-lite,gemini-2.0-flash").split(",")
    if model.strip()
]
if not MODEL_CANDIDATES:
    MODEL_CANDIDATES = ["gemini-3.1-flash-lite"]

CURRENT_MODEL = os.environ.get("MODEL_NAME", MODEL_CANDIDATES[0])

if CURRENT_MODEL not in MODEL_CANDIDATES:
    MODEL_CANDIDATES.insert(0, CURRENT_MODEL)

google_api_key = os.environ.get("GOOGLE_API_KEY")
gemini_base_url = os.environ.get("GEMINI_BASE_URL")
openai = OpenAI(api_key=google_api_key, base_url=gemini_base_url)

system = [{"role": "system", "content": TWIN_SYSTEM_PROMPT}]


def _is_quota_error(error):
    message = str(error).lower()
    return any(
        token in message
        for token in ["quota", "insufficient_quota", "429", "rate limit", "too many requests"]
    )


def _create_completion(messages, tools):
    global CURRENT_MODEL

    ordered_models = [CURRENT_MODEL] + [model for model in MODEL_CANDIDATES if model != CURRENT_MODEL]
    last_error = None

    for model_name in ordered_models:
        try:
            response = openai.chat.completions.create(model=model_name, messages=messages, tools=tools)
            CURRENT_MODEL = model_name
            return response
        except Exception as error:
            last_error = error
            if not _is_quota_error(error):
                raise

    if last_error is not None:
        raise last_error

    raise RuntimeError("No models available for completion")


def chat(message, history):
    messages = system + history + [{"role": "user", "content": message}]
    response = _create_completion(messages, tools)
    while response.choices[0].finish_reason == "tool_calls":
        message = response.choices[0].message
        tool_calls = message.tool_calls
        results = handle_tool_calls(tool_calls)
        messages.append(message)
        messages.extend(results)
        response = _create_completion(messages, tools)
    return response.choices[0].message.content


if __name__ == "__main__":
    gr.ChatInterface(
        chat,
        examples=EXAMPLES,
        title="Digital Twin",
        description="Talk to my AI twin about my career",
        chatbot=gr.Chatbot(show_label=False),
    ).launch(
        css=CSS,
        js=JS,
        theme=gr.themes.Base(),
        server_name="0.0.0.0",
        server_port=int(os.environ.get("PORT", 7860)),
    )
