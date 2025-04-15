async function query(data) {
    const response = await fetch(
        "https://router.huggingface.co/hf-inference/models/distilbert/distilbert-base-cased-distilled-squad",
        {
            headers: {
                Authorization: "Bearer hf_xxxxxxxxxxxxxxxxxxxxxxxx",
                "Content-Type": "application/json",
            },
            method: "POST",
            body: JSON.stringify(data),
        }
    );
    const result = await response.json();
    return result;
}

query({ inputs: {
    "question": "What is my name?",
    "context": "My name is Clara and I live in Berkeley."
} }).then((response) => {
    console.log(JSON.stringify(response));
    const answer = response.answer;
    document.getElementById("answer-container").textContent = "The answer is: " + answer;
});