import { writeFileSync, mkdirSync } from "fs";

const VOICE_ID = process.env.ELEVENLABS_VOICE_ID!;
const API_KEY = process.env.ELEVENLABS_API_KEY!;

const scenes = [
  { id: "scene-01-logo", text: "Pryvora. The digital obsidian." },
  { id: "scene-02-hook", text: "Your workspace. Your rules. Built for people who take their work seriously." },
  { id: "scene-03-dashboard", text: "See everything that matters. Overdue tasks, today's agenda, your latest notes — all in one place." },
  { id: "scene-04-tasks", text: "Track priorities. Set deadlines. Execute with precision." },
  { id: "scene-05-notes", text: "A private vault for every thought that matters." },
  { id: "scene-06-security", text: "End-to-end encrypted. Your data never leaves your control." },
  { id: "scene-07-outro", text: "Pryvora. Start for free." },
];

mkdirSync("public/voiceover", { recursive: true });

for (const scene of scenes) {
  console.log(`Generating: ${scene.id}...`);

  const response = await fetch(
    `https://api.elevenlabs.io/v1/text-to-speech/${VOICE_ID}`,
    {
      method: "POST",
      headers: {
        "xi-api-key": API_KEY,
        "Content-Type": "application/json",
        Accept: "audio/mpeg",
      },
      body: JSON.stringify({
        text: scene.text,
        model_id: "eleven_multilingual_v2",
        voice_settings: {
          stability: 0.5,
          similarity_boost: 0.75,
          style: 0.3,
        },
      }),
    },
  );

  if (!response.ok) {
    throw new Error(`ElevenLabs error for ${scene.id}: ${response.statusText}`);
  }

  const audioBuffer = Buffer.from(await response.arrayBuffer());
  writeFileSync(`public/voiceover/${scene.id}.mp3`, audioBuffer);
  console.log(`✓ Saved: public/voiceover/${scene.id}.mp3`);
}

console.log("✅ All voiceover files generated!");
