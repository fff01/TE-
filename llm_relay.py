import json
import os
import ssl
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.request import Request, build_opener, ProxyHandler, HTTPSHandler


HOST = "127.0.0.1"
PORT = int(os.getenv("BIOLOGY_LLM_RELAY_PORT", "18087"))
SSL_VERIFY = os.getenv("DASHSCOPE_SSL_VERIFY_BIOLOGY", os.getenv("DASHSCOPE_SSL_VERIFY", "0")).lower() in {
    "1", "true", "yes", "on"
}

PROVIDERS = {
    "qwen": {
        "url": os.getenv(
            "DASHSCOPE_API_URL_BIOLOGY",
            os.getenv("DASHSCOPE_API_URL", "https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions"),
        ),
        "model": os.getenv(
            "DASHSCOPE_MODEL_BIOLOGY",
            os.getenv("DASHSCOPE_MODEL", "qwen3.5-plus"),
        ),
        "key": os.getenv(
            "DASHSCOPE_API_KEY_BIOLOGY",
            os.getenv("DASHSCOPE_API_KEY", ""),
        ),
    },
    "deepseek": {
        "url": os.getenv("DEEPSEEK_API_URL", "https://api.deepseek.com/v1/chat/completions"),
        "model": os.getenv("DEEPSEEK_MODEL", "deepseek-chat"),
        "key": os.getenv("DEEPSEEK_API_KEY", ""),
    },
}


def build_provider_opener(url: str):
    handlers = [ProxyHandler({})]
    if url.startswith("https://"):
        context = ssl.create_default_context()
        if not SSL_VERIFY:
            context.check_hostname = False
            context.verify_mode = ssl.CERT_NONE
        handlers.append(HTTPSHandler(context=context))
    return build_opener(*handlers)


class RelayHandler(BaseHTTPRequestHandler):
    def _json(self, status, payload):
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if self.path != "/health":
            self._json(404, {"ok": False, "error": "Not found"})
            return
        self._json(200, {
            "ok": True,
            "service": "biology-llm-relay",
            "default_provider": "qwen",
            "providers": {
                name: {
                    "url": cfg["url"],
                    "model": cfg["model"],
                    "key_present": bool(cfg["key"]),
                }
                for name, cfg in PROVIDERS.items()
            },
            "ssl_verify": SSL_VERIFY,
            "proxy_bypassed": True,
        })

    def do_POST(self):
        if self.path != "/chat":
            self._json(404, {"ok": False, "error": "Not found"})
            return

        try:
            length = int(self.headers.get("Content-Length", "0"))
            raw = self.rfile.read(length)
            incoming = json.loads(raw.decode("utf-8") or "{}")
            messages = incoming.get("messages") or []
            temperature = incoming.get("temperature", 0.2)
            provider = str(incoming.get("provider") or incoming.get("model_provider") or "qwen").strip().lower()
            if provider not in PROVIDERS:
                self._json(400, {"ok": False, "error": f"Unsupported provider: {provider}"})
                return
            provider_config = PROVIDERS[provider]
            model = incoming.get("model") or provider_config["model"]
            enable_thinking = incoming.get("enable_thinking", False)

            if not provider_config["key"]:
                self._json(500, {"ok": False, "error": f"{provider} API key is missing"})
                return

            payload = json.dumps({
                "model": model,
                "messages": messages,
                "temperature": temperature,
                "enable_thinking": enable_thinking,
            }, ensure_ascii=False).encode("utf-8")

            req = Request(
                provider_config["url"],
                data=payload,
                headers={
                    "Content-Type": "application/json",
                    "Authorization": f"Bearer {provider_config['key']}",
                    "Connection": "close",
                    "Accept": "application/json",
                },
                method="POST",
            )

            opener = build_provider_opener(provider_config["url"])
            with opener.open(req, timeout=90) as resp:
                content = resp.read().decode("utf-8")
            decoded = json.loads(content)
            self._json(200, {"ok": True, "response": decoded})
        except Exception as exc:
            self._json(500, {"ok": False, "error": str(exc)})

    def log_message(self, format, *args):
        return


if __name__ == "__main__":
    server = ThreadingHTTPServer((HOST, PORT), RelayHandler)
    print(f"LLM relay listening on http://{HOST}:{PORT}")
    server.serve_forever()
