import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate } from "remotion";

export const SceneOutro: React.FC = () => {
  const frame = useCurrentFrame();

  const titleOpacity = interpolate(
    frame,
    [0, 40],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const titleTranslateY = interpolate(
    frame,
    [0, 40],
    [20, 0],
    { extrapolateRight: "clamp" }
  );

  const subtitleOpacity = interpolate(
    frame,
    [20, 60],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const subtitleTranslateY = interpolate(
    frame,
    [20, 60],
    [20, 0],
    { extrapolateRight: "clamp" }
  );

  const ctaOpacity = interpolate(
    frame,
    [100, 140],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  return (
    <AbsoluteFill
      style={{
        backgroundColor: "#0d0d0f",
        backgroundImage: "radial-gradient(circle at center, #1a1a2e 0%, #0d0d0f 70%)",
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        justifyContent: "center",
      }}
    >
      <div
        style={{
          fontSize: "100px",
          fontWeight: 800,
          color: "#f0f0f0",
          opacity: titleOpacity,
          transform: `translateY(${titleTranslateY}px)`,
          letterSpacing: "-0.02em",
        }}
      >
        Pryvora
      </div>
      <div
        style={{
          fontSize: "13px",
          fontWeight: 500,
          color: "#6b6b80",
          letterSpacing: "0.3em",
          textTransform: "uppercase",
          marginTop: "16px",
          opacity: subtitleOpacity,
          transform: `translateY(${subtitleTranslateY}px)`,
        }}
      >
        The Digital Obsidian
      </div>
      <div
        style={{
          fontSize: "20px",
          fontWeight: 600,
          color: "#7c6df0",
          marginTop: "48px",
          opacity: ctaOpacity,
        }}
      >
        Start for free →
      </div>
    </AbsoluteFill>
  );
};
