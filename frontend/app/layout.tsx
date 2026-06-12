import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Movie AI Chatbot",
  description: "Ollama와 영화 데이터를 활용한 영화 정보 챗봇",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="ko">
      <body>{children}</body>
    </html>
  );
}