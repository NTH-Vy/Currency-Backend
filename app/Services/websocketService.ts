// services/websocketService.ts
export interface WebSocketMessage {
  type: 'rates_update' | 'top_movers_update' | 'price_update' | 'connection_status';
  data: any;
  timestamp: string;
}

export interface RateUpdate {
  pair: string;
  price: string;
  change: string;
  trend: string;
  volatility: string;
  volume: string;
}

export interface TopMoverUpdate {
  pair: string;
  price: string;
  change: string;
  trend: string;
}

export class WebSocketService {
  private static instance: WebSocketService;
  private messageHandlers: Map<string, Set<(data: any) => void>> = new Map();
  
  private constructor() {}

  static getInstance(): WebSocketService {
    if (!WebSocketService.instance) {
      WebSocketService.instance = new WebSocketService();
    }
    return WebSocketService.instance;
  }

  // Đăng ký handler cho một loại message cụ thể
  subscribe(messageType: string, handler: (data: any) => void): () => void {
    if (!this.messageHandlers.has(messageType)) {
      this.messageHandlers.set(messageType, new Set());
    }
    
    const handlers = this.messageHandlers.get(messageType)!;
    handlers.add(handler);
    
    // Trả về function để unsubscribe
    return () => {
      handlers.delete(handler);
      if (handlers.size === 0) {
        this.messageHandlers.delete(messageType);
      }
    };
  }

  // Xử lý message nhận được từ WebSocket
  handleMessage(message: WebSocketMessage): void {
    const { type, data } = message;
    
    if (this.messageHandlers.has(type)) {
      const handlers = this.messageHandlers.get(type)!;
      handlers.forEach(handler => {
        try {
          handler(data);
        } catch (error) {
          console.error(`Error in handler for ${type}:`, error);
        }
      });
    }
  }

  // Xử lý nhiều messages cùng lúc (batch)
  handleBatchMessages(messages: WebSocketMessage[]): void {
    messages.forEach(msg => this.handleMessage(msg));
  }

  // Clear tất cả handlers
  clearAllHandlers(): void {
    this.messageHandlers.clear();
  }
}