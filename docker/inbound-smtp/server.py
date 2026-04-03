import logging
import os
import sys
import time
from email import policy
from email.parser import BytesParser

import requests
from aiosmtpd.controller import Controller


logging.basicConfig(
    level=os.getenv("INBOUND_SMTP_LOG_LEVEL", "INFO").upper(),
    stream=sys.stdout,
    format="%(asctime)s %(levelname)s %(message)s",
)


def extract_text_body(message):
    if message.is_multipart():
        text_parts = []

        for part in message.walk():
            if part.get_content_maintype() == "multipart":
                continue

            content_disposition = part.get_content_disposition()
            if content_disposition == "attachment":
                continue

            content_type = part.get_content_type()
            try:
                payload = part.get_content()
            except Exception:
                payload = ""

            if not isinstance(payload, str):
                continue

            if content_type == "text/plain":
                text_parts.append(payload)

        if text_parts:
            return "\n\n".join(part.strip() for part in text_parts if part.strip())

        return None

    try:
        payload = message.get_content()
    except Exception:
        payload = ""

    return payload if isinstance(payload, str) and payload.strip() else None


def extract_html_body(message):
    if message.is_multipart():
        html_parts = []

        for part in message.walk():
            if part.get_content_maintype() == "multipart":
                continue

            content_disposition = part.get_content_disposition()
            if content_disposition == "attachment":
                continue

            if part.get_content_type() != "text/html":
                continue

            try:
                payload = part.get_content()
            except Exception:
                payload = ""

            if isinstance(payload, str) and payload.strip():
                html_parts.append(payload)

        if html_parts:
            return "\n".join(html_parts)

        return None

    if message.get_content_type() != "text/html":
        return None

    try:
        payload = message.get_content()
    except Exception:
        payload = ""

    return payload if isinstance(payload, str) and payload.strip() else None


class ToastitInboundHandler:
    def __init__(self):
        self.inbound_url = os.environ["TOASTIT_INBOUND_URL"]
        self.inbound_secret = os.getenv("TOASTIT_INBOUND_SECRET", "")
        self.timeout_seconds = int(os.getenv("TOASTIT_INBOUND_TIMEOUT_SECONDS", "10"))

    async def handle_DATA(self, server, session, envelope):
        message = BytesParser(policy=policy.default).parsebytes(envelope.original_content)
        sender = envelope.mail_from or message.get("From", "")
        subject = message.get("Subject", "")
        text_body = extract_text_body(message)
        html_body = extract_html_body(message)
        message_id = message.get("Message-ID", "")
        in_reply_to = message.get("In-Reply-To", "")
        references = message.get("References", "")

        logging.info(
            "Parsed inbound message subject=%s message_id=%s in_reply_to=%s references=%s",
            subject,
            message_id,
            in_reply_to,
            references,
        )

        for recipient in envelope.rcpt_tos:
            payload = {
                "recipient": recipient,
                "from": sender,
                "subject": subject,
                "text": text_body,
                "html": html_body,
                "messageId": message_id or None,
                "inReplyTo": in_reply_to or None,
                "references": references or None,
            }

            logging.info("Forwarding inbound email for recipient=%s subject=%s", recipient, subject)

            response = requests.post(
                self.inbound_url,
                json=payload,
                headers={
                    "X-Toastit-Inbound-Secret": self.inbound_secret,
                },
                timeout=self.timeout_seconds,
            )

            if response.status_code >= 400:
                logging.error(
                    "Toastit inbound API rejected email recipient=%s status=%s body=%s",
                    recipient,
                    response.status_code,
                    response.text,
                )
                return "451 Unable to process inbound email"

        return "250 Message accepted"


def main():
    host = os.getenv("INBOUND_SMTP_HOST", "0.0.0.0")
    port = int(os.getenv("INBOUND_SMTP_PORT", "2525"))
    controller = Controller(ToastitInboundHandler(), hostname=host, port=port)
    controller.start()
    logging.info("Inbound SMTP bridge listening on %s:%s", host, port)

    try:
        while True:
            time.sleep(3600)
    except KeyboardInterrupt:
        logging.info("Stopping inbound SMTP bridge")
    finally:
        controller.stop()


if __name__ == "__main__":
    main()
