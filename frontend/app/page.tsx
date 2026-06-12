"use client";

import { useEffect, useRef, useState } from "react";

type Message = {
  from: "user" | "bot";
  text: string;
};

const EXAMPLE_QUESTIONS = [
  "액션 영화 추천해줘",
  "주토피아 줄거리 알려줘",
  "공포 영화 중 볼 만한 거 있어?",
];

export default function HomePage() {
  const [input, setInput] = useState("");
  const [messages, setMessages] = useState<Message[]>([]);
  const [loading, setLoading] = useState(false);
  const scrollRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    if (!scrollRef.current) return;
    scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
  }, [messages, loading]);

  const sendMessage = async () => {
    const text = input.trim();

    if (!text || loading) return;

    setMessages((prev) => [...prev, { from: "user", text }]);
    setInput("");
    setLoading(true);

    try {
      const res = await fetch("/api/chat", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ prompt: text }),
      });

      const data: { response?: string } = await res.json();

      setMessages((prev) => [
        ...prev,
        {
          from: "bot",
          text: data.response ?? "응답을 읽지 못했습니다.",
        },
      ]);
    } catch (error) {
      console.error(error);

      setMessages((prev) => [
        ...prev,
        {
          from: "bot",
          text: "서버와 연결하는 중 문제가 발생했습니다.",
        },
      ]);
    } finally {
      setLoading(false);
    }
  };

  const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
    if (event.key === "Enter" && !event.nativeEvent.isComposing) {
      event.preventDefault();
      sendMessage();
    }
  };

  const handleExampleClick = (question: string) => {
    setInput(question);
  };

  return (
    <main className="min-h-screen bg-[#f5f5f2] px-4 py-8 text-neutral-900">
      <section className="mx-auto flex h-[84vh] min-h-[620px] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
        <header className="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
          <div>
            <h1 className="text-lg font-semibold tracking-tight">
              Movie AI Chatbot
            </h1>
            <p className="mt-1 text-sm text-neutral-500">
              영화 추천과 줄거리 요약을 도와주는 로컬 AI 챗봇
            </p>
          </div>

          <span className="hidden rounded-full border border-neutral-200 px-3 py-1 text-xs text-neutral-500 sm:block">
            Ollama + TMDB
          </span>
        </header>

        <div
          ref={scrollRef}
          className="flex-1 overflow-y-auto bg-[#fafafa] px-5 py-5"
        >
          {messages.length === 0 ? (
            <div className="flex h-full flex-col justify-center">
              <div className="max-w-md">
                <p className="text-xl font-semibold">
                  어떤 영화를 찾고 있나요?
                </p>
                <p className="mt-2 text-sm leading-6 text-neutral-500">
                  영화 제목을 입력하면 줄거리를 요약해주고, 장르를 입력하면
                  관련 영화를 추천합니다.
                </p>

                <div className="mt-5 flex flex-wrap gap-2">
                  {EXAMPLE_QUESTIONS.map((question) => (
                    <button
                      key={question}
                      type="button"
                      onClick={() => handleExampleClick(question)}
                      className="rounded-full border border-neutral-200 bg-white px-3 py-2 text-sm text-neutral-600 transition hover:border-neutral-400 hover:text-neutral-900"
                    >
                      {question}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          ) : (
            <div className="space-y-4">
              {messages.map((message, index) => (
                <div
                  key={`${message.from}-${index}`}
                  className={`flex ${
                    message.from === "user" ? "justify-end" : "justify-start"
                  }`}
                >
                  <div
                    className={`max-w-[82%] whitespace-pre-wrap rounded-2xl px-4 py-3 text-sm leading-6 ${
                      message.from === "user"
                        ? "rounded-br-sm bg-neutral-900 text-white"
                        : "rounded-bl-sm border border-neutral-200 bg-white text-neutral-800"
                    }`}
                  >
                    {message.text}
                  </div>
                </div>
              ))}

              {loading && (
                <div className="flex justify-start">
                  <div className="rounded-2xl rounded-bl-sm border border-neutral-200 bg-white px-4 py-3 text-sm text-neutral-500">
                    답변을 준비하고 있어요...
                  </div>
                </div>
              )}
            </div>
          )}
        </div>

        <div className="border-t border-neutral-200 bg-white p-4">
          <div className="flex gap-2">
            <input
              value={input}
              onChange={(event) => setInput(event.target.value)}
              onKeyDown={onKeyDown}
              placeholder="예: 주토피아 줄거리 알려줘"
              className="min-w-0 flex-1 rounded-xl border border-neutral-300 px-4 py-3 text-sm outline-none transition focus:border-neutral-900"
            />
            <button
              type="button"
              onClick={sendMessage}
              disabled={loading || !input.trim()}
              className="rounded-xl bg-neutral-900 px-5 py-3 text-sm font-medium text-white transition hover:bg-neutral-700 disabled:cursor-not-allowed disabled:opacity-40"
            >
              전송
            </button>
          </div>

          <p className="mt-2 text-center text-xs text-neutral-400">
            TMDB 영화 데이터를 참고해 Ollama가 답변을 생성합니다.
          </p>
        </div>
      </section>
    </main>
  );
}