export async function POST(request: Request) {
  try {
    const body = await request.json();

    const res = await fetch("http://localhost:8080/chat.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(body),
    });

    const data = await res.json();

    return Response.json(data, {
      status: res.status,
    });
  } catch (error) {
    console.error(error);

    return Response.json(
      { response: "Next.js API route에서 오류가 발생했습니다." },
      { status: 500 }
    );
  }
}