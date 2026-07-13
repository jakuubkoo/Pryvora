// src/remotion/src/PryvoraIntro.tsx
import { TransitionSeries, linearTiming } from "@remotion/transitions";
import { fade } from "@remotion/transitions/fade";
import { Audio } from "@remotion/media";
import { Sequence, staticFile } from "remotion";
import { SceneLogo } from "./scenes/SceneLogo";
import { SceneHook } from "./scenes/SceneHook";
import { SceneDashboard } from "./scenes/SceneDashboard";
import { SceneTasks } from "./scenes/SceneTasks";
import { SceneNotes } from "./scenes/SceneNotes";
import { SceneSecurity } from "./scenes/SceneSecurity";
import { SceneOutro } from "./scenes/SceneOutro";

export type PryvoraIntroProps = {
  sceneDurations: number[];
};

const AUDIO_FILES = [
  "voiceover/scene-01-logo.mp3",
  "voiceover/scene-02-hook.mp3",
  "voiceover/scene-03-dashboard.mp3",
  "voiceover/scene-04-tasks.mp3",
  "voiceover/scene-05-notes.mp3",
  "voiceover/scene-06-security.mp3",
  "voiceover/scene-07-outro.mp3",
];

const TRANSITION = linearTiming({ durationInFrames: 20 });

export const PryvoraIntro = ({ sceneDurations }: PryvoraIntroProps) => {
  const [d0, d1, d2, d3, d4, d5, d6] = sceneDurations;

  // Calculate cumulative durations for audio sequence timing
  // Each audio starts 20 frames into its scene (after transition overlap)
  const audioStarts = [
    0,
    d0 - 20,
    d0 + d1 - 40,
    d0 + d1 + d2 - 60,
    d0 + d1 + d2 + d3 - 80,
    d0 + d1 + d2 + d3 + d4 - 100,
    d0 + d1 + d2 + d3 + d4 + d5 - 120,
  ];

  return (
    <>
      {/* Audio tracks — each delayed to match scene start in TransitionSeries */}
      <Sequence from={audioStarts[0]} premountFor={100}>
        <Audio src={staticFile(AUDIO_FILES[0])} volume={0.9} />
      </Sequence>
      <Sequence from={audioStarts[1]} premountFor={100}>
        <Audio src={staticFile(AUDIO_FILES[1])} volume={0.9} />
      </Sequence>
      <Sequence from={audioStarts[2]} premountFor={100}>
        <Audio src={staticFile(AUDIO_FILES[2])} volume={0.9} />
      </Sequence>
      <Sequence from={audioStarts[3]} premountFor={100}>
        <Audio src={staticFile(AUDIO_FILES[3])} volume={0.9} />
      </Sequence>
      <Sequence from={audioStarts[4]} premountFor={100}>
        <Audio src={staticFile(AUDIO_FILES[4])} volume={0.9} />
      </Sequence>
      <Sequence from={audioStarts[5]} premountFor={100}>
        <Audio src={staticFile(AUDIO_FILES[5])} volume={0.9} />
      </Sequence>
      <Sequence from={audioStarts[6]} premountFor={100}>
        <Audio src={staticFile(AUDIO_FILES[6])} volume={0.9} />
      </Sequence>

      {/* Scenes */}
      <TransitionSeries>
        <TransitionSeries.Sequence durationInFrames={d0} premountFor={100}>
          <SceneLogo />
        </TransitionSeries.Sequence>
        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={d1} premountFor={100}>
          <SceneHook />
        </TransitionSeries.Sequence>
        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={d2} premountFor={100}>
          <SceneDashboard />
        </TransitionSeries.Sequence>
        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={d3} premountFor={100}>
          <SceneTasks />
        </TransitionSeries.Sequence>
        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={d4} premountFor={100}>
          <SceneNotes />
        </TransitionSeries.Sequence>
        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={d5} premountFor={100}>
          <SceneSecurity />
        </TransitionSeries.Sequence>
        <TransitionSeries.Transition presentation={fade()} timing={TRANSITION} />

        <TransitionSeries.Sequence durationInFrames={d6} premountFor={100}>
          <SceneOutro />
        </TransitionSeries.Sequence>
      </TransitionSeries>
    </>
  );
};
