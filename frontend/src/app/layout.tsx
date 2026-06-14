import type { Metadata } from 'next';
import './globals.css';
import { Providers } from '@/components/Providers';

export const metadata: Metadata = {
  title: 'Gmail Auto Responder',
  description: 'Classify inbound Gmail and generate draft replies',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
